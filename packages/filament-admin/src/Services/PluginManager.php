<?php

namespace FilamentAdmin\Services;

use Composer\InstalledVersions;
use FilamentAdmin\Models\Plugin;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;

/**
 * 插件管理服务（基础版）
 *
 * 负责插件的启用、禁用、扫描同步、初始化和依赖检查。
 * validatePackageName（需 HTTP）等扩展方法可在宿主项目中子类化添加。
 */
class PluginManager
{
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
        Cache::forget("{$plugin->slug}:is_enabled");
    }

    /**
     * 禁用插件
     */
    public function disable(Plugin $plugin): void
    {
        $plugin->update(['is_enabled' => false]);
        Cache::forget('plugins.enabled_list');
        Cache::forget("{$plugin->slug}:is_enabled");
    }

    /**
     * 从 vendor/composer/installed.json 同步已安装插件到数据库（混合发现）
     *
     * 每个已安装包通过 detectPluginClass 判断是否为 Filament 插件。
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
        $data  = json_decode(file_get_contents($installedJson), true) ?? [];
        $count = 0;

        foreach ($data['packages'] ?? [] as $pkg) {
            /** @var array<string, mixed>|null $meta */
            $meta = $pkg['extra']['filament-admin'] ?? null;

            // 是否为 Filament 插件：有 extra.filament-admin 声明，或接口 classmap grep 发现
            $pluginClass = $this->detectPluginClass($pkg);

            if ($pluginClass === null && $meta === null) {
                continue;
            }

            $packageName = $pkg['name'];
            $slug        = $meta['slug'] ?? str($packageName)->replace('/', '-')->value();
            $existing    = Plugin::where('package_name', $packageName)->first();

            // 元数据优先读 extra.filament-admin；无则回落 composer.json 标准字段
            Plugin::updateOrCreate(
                ['package_name' => $packageName],
                [
                    'slug'               => $slug,
                    'name'               => $meta['name'] ?? $pkg['description'] ?? $packageName,
                    'kind'               => $meta['type'] ?? 'package',
                    'plugin_class'       => $pluginClass ?? $meta['plugin_class'] ?? null,
                    'settings_page_slug' => $meta['settings_page_slug'] ?? null,
                    'service_provider'   => $meta['service_provider'] ?? null,
                    'installed_version'  => $pkg['version'] ?? null,
                    'description'        => $meta['description'] ?? $pkg['description'] ?? null,
                    'source'             => $meta['source'] ?? 'community',
                    'post_install_data'  => $meta['post_install'] ?? null,
                    'installed_at'       => $existing?->installed_at ?? now(),
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
     * 混合发现：从包元数据中检测 Filament Plugin 接口实现类名
     *
     * 策略一：extra.filament-admin.plugin_class 直接返回（一方包快速路径）。
     * 策略二：遍历 vendor/composer/autoload_classmap.php，过滤属于该包的条目，
     *         读取源文件内容 grep Plugin 接口特征字符串，排除 /tests/ 路径和抽象类。
     * 不调用 class_exists / 不实例化任何类（T-12-01-01 威胁缓解）。
     *
     * @param  array<string, mixed>  $pkg  installed.json 中的单个包对象
     */
    private function detectPluginClass(array $pkg): ?string
    {
        // 策略一：一方包快速路径
        if ($class = $pkg['extra']['filament-admin']['plugin_class'] ?? null) {
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

        // 跳过抽象类
        if (str_contains($content, 'abstract class')) {
            return false;
        }

        return str_contains($content, $interfaceMarker) ||
               str_contains($content, 'implements Plugin');
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
     */
    protected function runSeeder(string $slug, Plugin $plugin): void
    {
        $seederArgs = ['--force' => true];

        if ($plugin->plugin_class) {
            $seederClass = str($plugin->plugin_class)
                ->beforeLast('\\')
                ->append('\\DatabaseSeeder')
                ->toString();

            if (class_exists($seederClass)) {
                $seederArgs['--class'] = $seederClass;
            }
        }

        Artisan::call('db:seed', $seederArgs);
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
