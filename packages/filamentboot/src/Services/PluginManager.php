<?php

namespace Filamentboot\Services;

use Composer\InstalledVersions;
use Filamentboot\Jobs\ComposerInstallJob;
use Filamentboot\Jobs\ComposerRemoveJob;
use Filamentboot\Models\Plugin;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use Symfony\Component\Process\Process;

/**
 * 插件管理服务（基础版）
 *
 * 负责插件的启用、禁用、扫描同步、初始化和依赖检查。
 * validatePackageName（需 HTTP）等扩展方法可在宿主项目中子类化添加。
 */
class PluginManager
{
    /**
     * 插件启用状态的缓存键——本类与各插件 ServiceProvider 的 pluginIsEnabled()
     * 必须用同一个键才能互相失效，此前两边各自硬编码 "{$slug}:is_enabled" 字面量，
     * 改一处很容易漏改另一处（批次 4 起提成共享方法）。
     */
    public static function isEnabledCacheKey(string $slug): string
    {
        return "{$slug}:is_enabled";
    }

    /**
     * 启用插件
     *
     * @throws \RuntimeException 当 compatibility 不满足时抛出
     */
    public function enable(Plugin $plugin): void
    {
        $issues = $this->checkDependencies($plugin);
        if (! empty($issues)) {
            throw new \RuntimeException(
                '插件兼容性不满足，无法启用：'.implode('；', $issues)
            );
        }

        $plugin->update(['is_enabled' => true]);
        Cache::forget('plugins.enabled_list');
        Cache::forget(self::isEnabledCacheKey($plugin->slug));
    }

    /**
     * 禁用插件
     */
    public function disable(Plugin $plugin): void
    {
        $plugin->update(['is_enabled' => false]);
        Cache::forget('plugins.enabled_list');
        Cache::forget(self::isEnabledCacheKey($plugin->slug));
    }

    /**
     * 从 vendor/composer/installed.json 同步已安装插件到数据库（混合发现）
     *
     * 每个已安装包通过 detectPluginClass 判断是否为 Filament 插件。
     * 同时根据包的 require.filament/filament 约束计算三态兼容性并持久化（CR-04）。
     *
     * @return int 同步的插件数量
     */
    public function syncFromInstalled(): int
    {
        $installedJson = base_path('vendor/composer/installed.json');

        if (! file_exists($installedJson)) {
            return 0;
        }

        /** @var array{packages: array<int, array<string, mixed>>} $data */
        $data       = json_decode(file_get_contents($installedJson), true) ?? [];
        $compat     = app(PluginCompatibility::class);
        $count      = 0;

        foreach ($data['packages'] ?? [] as $pkg) {
            /** @var array<string, mixed>|null $meta */
            // 读 extra.filamentboot（唯一规范键，D-05 硬切，不向后兼容）
            $meta = $pkg['extra']['filamentboot'] ?? null;

            // 是否为 Filament 插件：有 extra.filamentboot 声明，或接口 classmap grep 发现
            $pluginClass = $this->detectPluginClass($pkg);

            if ($pluginClass === null && $meta === null) {
                continue;
            }

            $packageName = $pkg['name'];
            $slug        = $meta['slug'] ?? str($packageName)->replace('/', '-')->value();
            $existing    = Plugin::where('package_name', $packageName)->first();

            // CR-04：从包的 require 字段提取 filament/filament 约束，计算三态兼容性
            $filamentConstraint    = $pkg['require']['filament/filament'] ?? null;
            $compatibilityStatus   = $compat->checkFilamentCompatibility($filamentConstraint);

            // 元数据读 extra.filamentboot；无则回落 composer.json 标准字段
            Plugin::updateOrCreate(
                ['package_name' => $packageName],
                [
                    'slug'                 => $slug,
                    'name'                 => $meta['name'] ?? $pkg['description'] ?? $packageName,
                    'kind'                 => $meta['type'] ?? 'package',
                    'plugin_class'         => $pluginClass ?? $meta['plugin_class'] ?? null,
                    'settings_page_slug'   => $meta['settings_page_slug'] ?? null,
                    'service_provider'     => $meta['service_provider'] ?? null,
                    'installed_version'    => $pkg['version'] ?? null,
                    'description'          => $meta['description'] ?? $pkg['description'] ?? null,
                    'source'               => $meta['source'] ?? 'community',
                    'post_install_data'    => $meta['post_install'] ?? null,
                    'compatibility_status' => $compatibilityStatus,
                    'installed_at'         => $existing?->installed_at ?? now(),
                ]
            );

            $count++;
        }

        Cache::forget('plugins.enabled_list');

        return $count;
    }

    /**
     * 同步初始化插件（migrate + vendor:publish + db:seed）
     */
    public function initialize(Plugin $plugin): void
    {
        set_time_limit(120);

        $slug = $plugin->slug;

        $plugin->update(['init_status' => 'running']);
        Cache::put("plugin.init.{$slug}", ['status' => 'running', 'logs' => []], 300);

        try {
            $this->runMigrate($slug);
            $this->runPublish($slug, $plugin);
            $this->runSeeder($slug, $plugin);

            $plugin->update(['init_status' => 'done']);
            $this->updateCacheStatus($slug, 'done');
        } catch (\Throwable $e) {
            $this->appendInitLog($slug, '初始化失败：'.$e->getMessage());
            $log = $this->getInitLog($slug);
            $plugin->update([
                'init_status' => 'failed',
                'init_log'    => $log,
            ]);
            $this->updateCacheStatus($slug, 'failed');
        }
    }

    /**
     * 检查插件依赖兼容性
     *
     * Phase 12: compatibility 字段已迁移至 Packagist p2 端点（MKTPLACE-05，Plan 02）。
     * 本方法保留供 Plan 02 扩展，当前版本返回空数组（无阻塞）。
     *
     * @return list<string> 不满足项提示，空数组表示全部兼容
     */
    public function checkDependencies(Plugin $plugin): array
    {
        return [];
    }

    /**
     * 触发后台 composer 安装（先环境自检，通过则 dispatch ComposerInstallJob）
     *
     * 若自检失败，设置 init_status='failed' 并记录降级提示（Plan 04 UI 展示手动命令）。
     */
    public function install(Plugin $plugin): void
    {
        $check = app(EnvironmentChecker::class)->check();

        if (! $check['ok']) {
            $issues = implode('；', $check['issues']);
            $plugin->update([
                'init_status' => 'failed',
                'init_log'    => "环境自检失败，请手动安装：{$issues}",
            ]);

            return;
        }

        $plugin->update(['init_status' => 'running']);
        ComposerInstallJob::dispatch($plugin->id, $plugin->package_name);
    }

    /**
     * 触发后台 composer 卸载（先 disable，再 dispatch ComposerRemoveJob）
     *
     * 先 disable 防止下次请求 class-not-found（RESEARCH Pitfall 5）。
     */
    public function uninstall(Plugin $plugin, bool $dropTables = false): void
    {
        $this->disable($plugin);
        ComposerRemoveJob::dispatch($plugin->id, $plugin->package_name, $dropTables);
    }

    /**
     * post-install 生命周期：vendor:publish（按声明 tags）+ migrate --force + dump-autoload
     *
     * 任意步骤失败仅记日志，不致命（D-12-04）。
     * 社区插件未声明 post_install 块时走通用兜底（Pattern 6）。
     */
    public function postInstall(Plugin $plugin): void
    {
        $slug = $plugin->slug;
        $data = $plugin->post_install_data ?? [];

        $this->runPostPublish($slug, $plugin, $data);
        $this->runPostMigrate($slug);
        $this->runPostSeeders($slug, $data);
        $this->runDumpAutoload($slug);
    }

    /**
     * 执行 composer require 流程（供 ComposerInstallJob::handle 委托）
     *
     * 严格包名验证（接收裸包名，不含约束冒号）→ 构建版本约束 require 参数 →
     * 构建 Process → 流式追加 init_log → 成功后 postInstall。
     *
     * 约束来源（CR-01）：优先读 Plugin.install_constraint（由 catalog/community 条目写入，
     * 供 composer require 使用），回落至 Plugin.installed_version（仅用于向后兼容已有行）。
     * installed_version 由 syncFromInstalled 写入真实解析版本，不应再用作安装约束。
     */
    public function runComposerInstall(Plugin $plugin, string $packageName): void
    {
        $slug = $plugin->slug;

        // validatePackageName 仅接收裸包名（regex 禁止 ':'），不传 package:constraint 字符串
        if (! $this->validatePackageName($packageName)) {
            $plugin->update(['init_status' => 'failed', 'init_log' => "包名格式无效：{$packageName}"]);

            return;
        }

        // CR-01：优先使用 install_constraint（明确的安装约束），回落至 installed_version（向后兼容）。
        // 确保 syncFromInstalled 写入的精确解析版本不会覆盖用户原始约束。
        $constraint  = $plugin->install_constraint ?? $plugin->installed_version;
        $requireArg  = ($constraint !== null && $constraint !== '')
            ? $packageName.':'.$constraint
            : $packageName;

        $composerPath = $this->resolveComposerExec();
        $process      = $this->buildComposerProcess([$composerPath, 'require', $requireArg, '--no-interaction', '--no-ansi']);

        $logLines = [];
        try {
            $process->start(function (string $type, string $data) use ($plugin, &$logLines): void {
                $this->appendProcessOutput($plugin, $logLines, $data);
            });
            $process->wait();

            if ($process->isSuccessful()) {
                $plugin->update(['init_status' => 'done', 'init_log' => implode("\n", $logLines)]);
                $this->postInstall($plugin);
            } else {
                $plugin->update(['init_status' => 'failed', 'init_log' => implode("\n", $logLines)]);
            }
        } catch (\Throwable $e) {
            $logLines[] = 'ERROR: '.$e->getMessage();
            $plugin->update(['init_status' => 'failed', 'init_log' => implode("\n", $logLines)]);
        }
    }

    /**
     * 执行 composer remove 流程（供 ComposerRemoveJob::handle 委托）
     *
     * disable → composer remove → 可选删表（WR-02）→ delete plugins 记录 → optimize:clear。
     *
     * @param  bool  $dropTables  是否删除插件自建数据表（来自 post_install_data.tables）
     */
    public function runComposerRemove(Plugin $plugin, string $packageName, bool $dropTables = false): void
    {
        $logLines     = [];
        $composerPath = $this->resolveComposerExec();

        if ($packageName !== '' && ! $this->validatePackageName($packageName)) {
            Log::warning("[PluginManager] 包名格式无效，跳过 composer remove：{$packageName}");
        } elseif ($packageName !== '') {
            $process = $this->buildComposerProcess([$composerPath, 'remove', $packageName, '--no-interaction', '--no-ansi']);
            try {
                $process->start(function (string $type, string $data) use (&$logLines): void {
                    $logLines[] = trim($data);
                });
                $process->wait();
            } catch (\Throwable $e) {
                Log::error("[PluginManager] composer remove 失败：{$e->getMessage()}");
            }
        }

        // WR-02：删除插件自建数据表（仅在勾选且插件有声明时执行）
        if ($dropTables) {
            $tables = $plugin->post_install_data['tables'] ?? [];
            foreach ($tables as $table) {
                // 安全校验：只允许纯字母/数字/下划线表名，防止 SQL 注入
                if (is_string($table) && preg_match('/^\w+$/', $table)) {
                    Schema::dropIfExists($table);
                    Log::info("[PluginManager] 已删除表 {$table}（插件：{$plugin->slug}）");
                }
            }
        }

        $plugin->delete();
        Artisan::call('optimize:clear');
    }

    /**
     * 混合发现：从包元数据中检测 Filament Plugin 接口实现类名
     *
     * 策略一：extra.filamentboot.plugin_class 直接返回（一方包快速路径，D-05 唯一规范键）。
     * 策略二：遍历 vendor/composer/autoload_classmap.php，过滤属于该包的条目，
     *         读取源文件内容 grep Plugin 接口特征字符串，排除 /tests/ 路径和抽象类。
     * 不调用 class_exists / 不实例化任何类（T-12-01-01 威胁缓解）。
     *
     * @param  array<string, mixed>  $pkg  installed.json 中的单个包对象
     */
    private function detectPluginClass(array $pkg): ?string
    {
        // 策略一：一方包快速路径（D-05 硬切，仅读 extra.filamentboot，不向后兼容）
        if ($class = $pkg['extra']['filamentboot']['plugin_class'] ?? null) {
            return $class;
        }

        // 策略二：classmap grep
        return $this->classmapGrep($pkg);
    }

    /**
     * 从 autoload_classmap.php 和包的 autoload 源目录 grep 给定包的 Plugin 接口实现类
     *
     * 两步扫描：
     * 1. 全局 autoload_classmap.php 按包名过滤（已安装且已 dump-autoload -o 的包）
     * 2. 包 installed.json autoload 声明的源目录（PSR-4/classmap/files）递归扫描 PHP 文件
     *
     * @param  array<string, mixed>  $pkg
     */
    private function classmapGrep(array $pkg): ?string
    {
        // 接口特征标记（Filament Plugin 合约的完全限定名）
        $interfaceMarker = 'Filament\\Contracts\\Plugin';
        $packageName     = $pkg['name'] ?? '';

        // 步骤一：全局 classmap（已优化 dump 的包）
        $classMapFile = base_path('vendor/composer/autoload_classmap.php');
        if (file_exists($classMapFile)) {
            /** @var array<string, string> $classmap */
            $classmap = require $classMapFile;

            if (empty($classmap)) {
                Log::debug('[PluginManager] autoload_classmap.php 为空，请运行 composer dump-autoload -o');
            }

            foreach ($classmap as $className => $filePath) {
                if (! str_contains($filePath, $packageName)) {
                    continue;
                }
                $found = $this->fileMatchesPluginInterface($filePath, $interfaceMarker);
                if ($found) {
                    return $className;
                }
            }
        }

        // 步骤二：扫描包 autoload 声明的源目录（处理未 dump-autoload -o 的包）
        return $this->scanPackageSourceDirs($pkg, $interfaceMarker);
    }

    /**
     * 扫描包 installed.json autoload 声明的目录，查找 Plugin 接口实现类
     *
     * @param  array<string, mixed>  $pkg
     */
    private function scanPackageSourceDirs(array $pkg, string $interfaceMarker): ?string
    {
        $packageVendorPath = base_path('vendor/'.($pkg['name'] ?? ''));

        if (! is_dir($packageVendorPath)) {
            return null;
        }

        /** @var array<string, mixed> $autoload */
        $autoload = $pkg['autoload'] ?? [];

        // 收集要扫描的目录（PSR-4 和 classmap 条目）
        // PSR-4 的值可能是字符串或字符串数组（Composer 规范允许）
        $sourceDirs = [];
        foreach ($autoload['psr-4'] ?? [] as $srcDir) {
            foreach ((array) $srcDir as $dir) {
                $sourceDirs[] = rtrim($packageVendorPath.'/'.$dir, '/');
            }
        }
        foreach ($autoload['classmap'] ?? [] as $srcDir) {
            foreach ((array) $srcDir as $dir) {
                $sourceDirs[] = rtrim($packageVendorPath.'/'.$dir, '/');
            }
        }

        if (empty($sourceDirs)) {
            $sourceDirs[] = $packageVendorPath.'/src';
        }

        foreach ($sourceDirs as $dir) {
            if (! is_dir($dir)) {
                continue;
            }
            $result = $this->grepDirForPlugin($dir, $interfaceMarker);
            if ($result !== null) {
                return $result;
            }
        }

        return null;
    }

    /**
     * 递归扫描目录中的 PHP 文件，返回第一个实现 Plugin 接口的类名（概念性）
     *
     * 返回文件路径作为类名标识符（调用方仅需判断是否非 null）。
     */
    private function grepDirForPlugin(string $dir, string $interfaceMarker): ?string
    {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS)
        );

        /** @var \SplFileInfo $file */
        foreach ($iterator as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }
            $found = $this->fileMatchesPluginInterface($file->getPathname(), $interfaceMarker);
            if ($found) {
                // 从文件路径提取类名（最终结果供 plugin_class 字段使用）
                return $this->extractClassNameFromFile($file->getPathname());
            }
        }

        return null;
    }

    /**
     * 检查单个 PHP 文件是否实现 Filament Plugin 接口
     *
     * 排除 /tests/ 路径（T-12-01-02）和抽象类（T-12-01-01）。
     *
     * WR-03：使用正则匹配 class 声明行中的 implements Plugin，而非纯字符串搜索。
     * 这样 use Filament\Contracts\Plugin 导入语句或方法参数 Plugin $p 不会误报。
     * 不调用 class_exists / 不实例化任何类（T-12-01-01 威胁缓解）。
     */
    private function fileMatchesPluginInterface(string $filePath, string $interfaceMarker): bool
    {
        // T-12-01-02：排除 /tests/ 路径
        if (str_contains($filePath, DIRECTORY_SEPARATOR.'tests'.DIRECTORY_SEPARATOR) ||
            str_contains($filePath, '/tests/')) {
            return false;
        }

        if (! file_exists($filePath)) {
            return false;
        }

        $content = file_get_contents($filePath);

        // 跳过抽象类（T-12-01-01）
        if (str_contains($content, 'abstract class')) {
            return false;
        }

        // WR-03：仅匹配 class 声明行中出现 implements ... Plugin
        // 正则说明：
        //   \bclass\s+\w+      → 类名声明
        //   [^{]*\bimplements\b → implements 关键字（类体 { 之前）
        //   [^{]*\bPlugin\b     → 接口名 Plugin（完整词边界，排除 PluginFoo 等误匹配）
        return (bool) preg_match(
            '/\bclass\s+\w+[^{]*\bimplements\b[^{]*\bPlugin\b/s',
            $content
        );
    }

    /**
     * 从 PHP 源文件内容提取完全限定类名
     *
     * @return string|null 完全限定类名，无法提取时返回文件路径
     */
    private function extractClassNameFromFile(string $filePath): ?string
    {
        $content   = file_get_contents($filePath);
        $namespace = null;
        $className = null;

        foreach (explode("\n", $content) as $line) {
            if ($namespace === null && preg_match('/^namespace\s+([\w\\\\]+)\s*;/', $line, $m)) {
                $namespace = $m[1];
            }
            if ($className === null && preg_match('/^(?:class|final\s+class)\s+(\w+)/', $line, $m)) {
                $className = $m[1];
            }
            if ($namespace !== null && $className !== null) {
                break;
            }
        }

        if ($className === null) {
            return null;
        }

        return $namespace !== null ? $namespace.'\\'.$className : $className;
    }

    /**
     * 执行数据库迁移
     */
    protected function runMigrate(string $slug): void
    {
        Artisan::call('migrate', ['--force' => true]);
        $this->appendInitLog($slug, '迁移完成：'.trim(Artisan::output()));
    }

    /**
     * 发布插件资源
     *
     * 使用 service_provider 字段（非 plugin_class）作 vendor:publish --provider 参数。
     */
    protected function runPublish(string $slug, Plugin $plugin): void
    {
        $serviceProvider = $plugin->service_provider ?? null;

        if (! $serviceProvider) {
            $this->appendInitLog($slug, '无 service_provider 声明，跳过资源发布。');

            return;
        }

        if (! is_subclass_of($serviceProvider, ServiceProvider::class)) {
            $this->appendInitLog($slug, "service_provider '{$serviceProvider}' 不是有效的 ServiceProvider，跳过。");

            return;
        }

        Artisan::call('vendor:publish', [
            '--provider' => $serviceProvider,
            '--force'    => true,
        ]);
        $this->appendInitLog($slug, '资源发布完成：'.trim(Artisan::output()));
    }

    /**
     * 执行插件 Seeder
     *
     * 仅在能够解析出具体 Seeder 类时执行 db:seed，否则跳过。
     * 不允许不带 --class 参数调用 db:seed，防止触发应用根 DatabaseSeeder（CR-01）。
     */
    protected function runSeeder(string $slug, Plugin $plugin): void
    {
        if (! $plugin->plugin_class) {
            $this->appendInitLog($slug, '无 plugin_class 声明，跳过数据填充。');

            return;
        }

        $seederClass = str($plugin->plugin_class)
            ->beforeLast('\\')
            ->append('\\DatabaseSeeder')
            ->toString();

        if (! class_exists($seederClass)) {
            $this->appendInitLog($slug, "未找到 Seeder 类 {$seederClass}，跳过数据填充。");

            return;
        }

        Artisan::call('db:seed', ['--class' => $seederClass, '--force' => true]);
        $this->appendInitLog($slug, '数据填充完成：'.trim(Artisan::output()));
    }

    /**
     * 获取当前运行环境的包版本映射
     *
     * @return array<string, string>
     */
    protected function getCurrentEnvironmentVersions(): array
    {
        return [
            'laravel'           => app()->version(),
            'filament'          => $this->resolvePackageVersion('filament/filament'),
            'filament/filament' => $this->resolvePackageVersion('filament/filament'),
            'laravel/framework' => $this->resolvePackageVersion('laravel/framework'),
            'php'               => PHP_VERSION,
        ];
    }

    /**
     * 从 Composer InstalledVersions 解析包版本
     */
    protected function resolvePackageVersion(string $packageName): string
    {
        try {
            if (InstalledVersions::isInstalled($packageName)) {
                return InstalledVersions::getPrettyVersion($packageName) ?? '0.0.0';
            }
        } catch (\Throwable) {
            // 忽略
        }

        return '0.0.0';
    }

    /**
     * 严格验证 composer 包名格式（T-12-02-01 命令注入缓解）
     *
     * 仅允许 lowercase 字母、数字、点、下划线、连字符的 vendor/package 格式。
     *
     * 可见性 protected（不是 private）：宿主 `App\Services\PluginManager` 用更严格的
     * 校验（白名单 + Packagist p2 API + semver）覆写此方法。若声明为 private，
     * 本类内部 `$this->validatePackageName()` 调用会静态绑定到本类自身，子类的
     * public 覆写永不触发——private 方法不参与虚方法分派，与对象实际类型无关
     * （四期基线 §4.1 记录的死代码即此）。
     */
    protected function validatePackageName(string $name): bool
    {
        return (bool) preg_match('/^[a-z0-9_.-]+\/[a-z0-9_.-]+$/', $name);
    }

    /**
     * 解析 composer 可执行路径（不抛异常，无法找到时返回 'composer'）
     *
     * WR-04：使用 config('filamentboot.composer_path') 代替 env('COMPOSER_PATH')，
     * 确保 config:cache 后仍能读取到配置值（env() 在缓存环境下返回 null）。
     *
     * 可见性 protected（供测试子类覆盖隔离真实 composer 调用）。
     */
    protected function resolveComposerExec(): string
    {
        if ($path = config('filamentboot.composer_path')) {
            if (is_executable($path)) {
                return $path;
            }
        }

        try {
            $process = new Process(['which', 'composer']);
            $process->run();
            $which = trim($process->getOutput());
            if ($which !== '') {
                return $which;
            }
        } catch (\Throwable) {
            // 继续下一步
        }

        return is_executable('/usr/local/bin/composer') ? '/usr/local/bin/composer' : 'composer';
    }

    /**
     * 构建隔离环境的 composer Process（T-12-02-01/02 缓解）
     *
     * 使用数组命令（永不 fromShellCommandline），设置 COMPOSER_HOME 隔离。
     * 可见性 protected（供测试子类覆盖隔离真实 composer 调用）。
     *
     * @param  list<string>  $command
     */
    protected function buildComposerProcess(array $command): Process
    {
        $process = new Process(
            command: $command,
            cwd: base_path(),
            env: [
                'COMPOSER_HOME'         => sys_get_temp_dir().'/composer-home-'.getmypid(),
                'COMPOSER_MEMORY_LIMIT' => '-1',
                'HOME'                  => sys_get_temp_dir(),
            ],
            timeout: 300.0,
        );
        $process->setIdleTimeout(60.0);

        return $process;
    }

    /**
     * 批量追加 Process 输出到 init_log（每 10 行写一次 DB）
     *
     * @param  list<string>  $logLines
     */
    private function appendProcessOutput(Plugin $plugin, array &$logLines, string $data): void
    {
        $logLines[] = trim($data);
        if (count($logLines) % 10 === 0) {
            $plugin->update(['init_log' => implode("\n", $logLines)]);
        }
    }

    /**
     * post-install: 执行 vendor:publish（按 post_install.publish_tags 声明，或 service_provider 兜底）
     *
     * 社区插件无声明时跳过发布，记日志（Pattern 6 fallback）。
     *
     * @param  array<string, mixed>  $data
     */
    private function runPostPublish(string $slug, Plugin $plugin, array $data): void
    {
        $tags = $data['publish_tags'] ?? [];

        if (! empty($tags)) {
            foreach ($tags as $tag) {
                Artisan::call('vendor:publish', ['--tag' => $tag, '--force' => true]);
                $this->appendInitLog($slug, '发布 tag='.$tag.'：'.trim(Artisan::output()));
            }

            return;
        }

        // 兜底：使用 service_provider（已有 runPublish 逻辑）
        if ($plugin->service_provider) {
            $this->runPublish($slug, $plugin);
        } else {
            $this->appendInitLog($slug, '无 publish_tags 且无 service_provider，跳过资源发布。');
        }
    }

    /**
     * post-install: 执行 migrate --force（Pitfall 6：Nothing to migrate 不视为失败）
     */
    private function runPostMigrate(string $slug): void
    {
        try {
            Artisan::call('migrate', ['--force' => true]);
            $output = trim(Artisan::output());
            $this->appendInitLog($slug, '迁移完成：'.($output !== '' ? $output : '（无待执行迁移）'));
        } catch (\Throwable $e) {
            $this->appendInitLog($slug, '迁移失败（非阻断）：'.$e->getMessage());
        }
    }

    /**
     * post-install: 执行声明的 Seeder 列表（社区插件无声明时跳过）
     *
     * @param  array<string, mixed>  $data
     */
    private function runPostSeeders(string $slug, array $data): void
    {
        $seeders = $data['seeders'] ?? [];
        foreach ($seeders as $seederClass) {
            try {
                Artisan::call('db:seed', ['--class' => $seederClass, '--force' => true]);
                $this->appendInitLog($slug, "Seeder {$seederClass} 完成：".trim(Artisan::output()));
            } catch (\Throwable $e) {
                $this->appendInitLog($slug, "Seeder {$seederClass} 失败（非阻断）：{$e->getMessage()}");
            }
        }
    }

    /**
     * post-install: 执行 composer dump-autoload（Pitfall 2 缓解）
     */
    private function runDumpAutoload(string $slug): void
    {
        $composerPath = $this->resolveComposerExec();

        try {
            $process = new Process([$composerPath, 'dump-autoload', '--no-interaction'], base_path(), timeout: 60.0);
            $process->run();
            $this->appendInitLog($slug, 'dump-autoload 完成。');
        } catch (\Throwable $e) {
            $this->appendInitLog($slug, 'dump-autoload 失败（非阻断）：'.$e->getMessage());
        }
    }

    private function appendInitLog(string $slug, string $line): void
    {
        $key     = "plugin.init.{$slug}";
        $current = Cache::get($key, ['status' => 'running', 'logs' => []]);
        $trimmed = trim($line);
        if ($trimmed !== '') {
            $current['logs'][] = $trimmed;
        }
        Cache::put($key, $current, 300);
    }

    private function updateCacheStatus(string $slug, string $status): void
    {
        $key               = "plugin.init.{$slug}";
        $current           = Cache::get($key, ['status' => $status, 'logs' => []]);
        $current['status'] = $status;
        Cache::put($key, $current, 300);
    }

    private function getInitLog(string $slug): string
    {
        $key     = "plugin.init.{$slug}";
        $current = Cache::get($key, ['logs' => []]);

        return implode("\n", $current['logs'] ?? []);
    }
}
