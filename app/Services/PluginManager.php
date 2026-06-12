<?php

namespace App\Services;

use App\Models\Plugin;
use Composer\Semver\Semver;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * 插件管理服务
 *
 * 负责插件的启用、禁用、扫描同步、初始化、包名校验和依赖检查。
 * 与 AdminPanelProvider 通过 Cache::forget('plugins.enabled_list') 协同（D-06-04）。
 */
class PluginManager
{
    /**
     * 启用插件，并清除 Panel 缓存
     *
     * @throws \RuntimeException 当 compatibility 不满足当前环境时抛出
     */
    public function enable(Plugin $plugin): void
    {
        // 兼容性检查：不满足时阻断（异常优先，CLAUDE.md）
        $issues = $this->checkDependencies($plugin);
        if (! empty($issues)) {
            throw new \RuntimeException(
                '插件兼容性不满足，无法启用：' . implode('；', $issues)
            );
        }

        $plugin->update(['is_enabled' => true]);

        // 清除 Panel 插件列表缓存（Pitfall 4：enable 后必须 forget）
        Cache::forget('plugins.enabled_list');
    }

    /**
     * 禁用插件，并清除 Panel 缓存
     */
    public function disable(Plugin $plugin): void
    {
        $plugin->update(['is_enabled' => false]);

        // 清除 Panel 插件列表缓存（Pitfall 4：disable 后必须 forget）
        Cache::forget('plugins.enabled_list');
    }

    /**
     * 从 vendor/composer/installed.json 同步已安装插件到数据库
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

            if ($meta === null) {
                continue;
            }

            $packageName = $pkg['name'];
            // slug 降级包含 vendor 前缀以避免唯一约束冲突（WR-03 修复）：
            // vendor-a/my-plugin 与 vendor-b/my-plugin 均缺省 slug 时，
            // afterLast('/') 均得到 my-plugin，触发 UNIQUE 约束违反。
            // 改为 replace('/', '-') 得到 vendor-a-my-plugin 确保唯一性。
            $slug        = $meta['slug'] ?? str($packageName)->replace('/', '-')->value();

            // 预查询已有记录，用于保留 installed_at（幂等：首次写入 now()，重复扫描保留原值）
            // CR-01 修复：updateOrCreate 属性数组不可含 Closure，否则 preg_match 收到 Closure 抛 TypeError
            $existing = Plugin::where('package_name', $packageName)->first();

            Plugin::updateOrCreate(
                ['package_name' => $packageName],
                [
                    'slug'              => $slug,
                    'name'              => $meta['name'] ?? $packageName,
                    'kind'              => $meta['type'] ?? 'package',
                    'plugin_class'      => $meta['plugin_class'] ?? null,
                    // service_provider 与 plugin_class 分离（WR-02 修复）：
                    // plugin_class 是 Filament Plugin 接口实现，
                    // service_provider 是 ServiceProvider 子类（供 vendor:publish 使用）
                    'service_provider'  => $meta['service_provider'] ?? null,
                    'installed_version' => $pkg['version'] ?? null,
                    'description'       => $meta['description'] ?? null,
                    'requires'          => $meta['requires'] ?? [],
                    'compatibility'     => $meta['compatibility'] ?? [],
                    'source'            => $meta['source'] ?? 'community',
                    // installed_at：保留旧值，首次写入时用 now()（预查询具体值，消除 Closure）
                    'installed_at'      => $existing?->installed_at ?? now(),
                ]
            );

            $count++;
        }

        Cache::forget('plugins.enabled_list');

        return $count;
    }

    /**
     * 同步初始化插件（migrate + vendor:publish + db:seed）
     *
     * 顺序执行三步，每步后增量写 Cache 进度日志。
     * 成功置 init_status=done；异常置 init_status=failed 并保留 init_log。
     * （D-06-04 方案型 solution_plugin 初始化链路，OQ1 同步执行裁决）
     */
    public function initialize(Plugin $plugin): void
    {
        // 同步初始化可能较慢，延长执行时限（Pitfall 3）
        set_time_limit(120);

        $slug = $plugin->slug;

        $plugin->update(['init_status' => 'running']);
        Cache::put("plugin.init.{$slug}", ['status' => 'running', 'logs' => []], 300);

        try {
            // 步骤 1：数据库迁移（天然幂等）
            $this->runMigrate($slug);

            // 步骤 2：发布资源（--force 覆盖，幂等）
            $this->runPublish($slug, $plugin);

            // 步骤 3：数据填充（依赖作者 updateOrCreate 幂等）
            $this->runSeeder($slug, $plugin);

            // 成功
            $plugin->update(['init_status' => 'done']);
            $this->updateCacheStatus($slug, 'done');
        } catch (\Throwable $e) {
            // 失败：保留日志，置 failed（不 rethrow，供详情页重试）
            $this->appendInitLog($slug, '初始化失败：' . $e->getMessage());
            $log = $this->getInitLog($slug);
            $plugin->update([
                'init_status' => 'failed',
                'init_log'    => $log,
            ]);
            $this->updateCacheStatus($slug, 'failed');
        }
    }

    /**
     * 执行数据库迁移（拆为独立方法便于测试 mock）
     */
    protected function runMigrate(string $slug): void
    {
        Artisan::call('migrate', ['--force' => true]);
        $this->appendInitLog($slug, '迁移完成：' . trim(Artisan::output()));
    }

    /**
     * 发布插件资源（拆为独立方法便于测试 mock）
     *
     * vendor:publish --provider 期望接收 ServiceProvider 子类，
     * plugin_class 存储的是 Filament Plugin 接口实现，两者不同（WR-02 修复）。
     * 插件须在 extra.filament-admin 中额外声明 service_provider 字段，
     * 若未声明则记录说明日志并跳过，避免 PublishCommand 静默空输出。
     */
    protected function runPublish(string $slug, Plugin $plugin): void
    {
        // 优先取 service_provider 字段（ServiceProvider 类名，供 vendor:publish 使用）
        // plugin_class 是 Filament Plugin 接口实现，不是 ServiceProvider，无法作 --provider 参数
        $serviceProvider = $plugin->service_provider ?? null;

        if (! $serviceProvider) {
            $this->appendInitLog($slug, '无 service_provider 声明，跳过资源发布。');

            return;
        }

        // 安全守卫：仅允许已注册的 ServiceProvider 子类，防止任意类被注入
        if (! is_subclass_of($serviceProvider, \Illuminate\Support\ServiceProvider::class)) {
            $this->appendInitLog($slug, "service_provider '{$serviceProvider}' 不是有效的 ServiceProvider，跳过资源发布。");

            return;
        }

        Artisan::call('vendor:publish', [
            '--provider' => $serviceProvider,
            '--force'    => true,
        ]);
        $this->appendInitLog($slug, '资源发布完成：' . trim(Artisan::output()));
    }

    /**
     * 执行插件 Seeder（拆为独立方法便于测试 mock）
     *
     * @throws \RuntimeException 当 seeder 执行失败时
     */
    protected function runSeeder(string $slug, Plugin $plugin): void
    {
        // 初始化命令参数全部来自 Plugin 声明字段，不接受用户输入（T-06-07 缓解）
        $seederArgs = ['--force' => true];
        if ($plugin->plugin_class) {
            // 取插件 plugin_class 同命名空间下的 DatabaseSeeder
            $seederClass = str($plugin->plugin_class)
                ->beforeLast('\\')
                ->append('\\DatabaseSeeder')
                ->toString();

            if (class_exists($seederClass)) {
                $seederArgs['--class'] = $seederClass;
            }
        }

        Artisan::call('db:seed', $seederArgs);
        $this->appendInitLog($slug, '数据填充完成：' . trim(Artisan::output()));
    }

    /**
     * 校验包名合法性（白名单直通 + Packagist p2 API 404 阻断 + semver 约束校验）
     *
     * （T-06-06 Typosquatting 缓解）
     */
    public function validatePackageName(string $packageName): bool
    {
        // 白名单来源检查（D-06-08 第一层）
        $entry = collect(config('official-market.entries', []))
            ->firstWhere('package_name', $packageName);

        if ($entry && in_array($entry['source'], ['official_trusted', 'official_listed'], true)) {
            return true; // 已在白名单，无需远程校验
        }

        // Packagist p2 API 校验（D-06-08 第二层）
        try {
            [$vendor, $name] = explode('/', $packageName, 2);
            $response        = Http::timeout(5)
                ->get("https://repo.packagist.org/p2/{$vendor}/{$name}.json");

            if (! $response->ok()) {
                return false; // 404 = 包不存在（阻断 typosquatting）
            }

            // 版本约束校验（D-06-10 轻量化）
            /** @var array<int, array<string, mixed>> $versions */
            $versions = $response->json("packages.{$packageName}", []);

            if (empty($versions)) {
                return false;
            }

            $latest     = $versions[0]['version'] ?? null;
            $constraint = $this->getCompatibilityConstraint($packageName);

            if ($latest && $constraint) {
                return Semver::satisfies($latest, $constraint);
            }

            return true;
        } catch (\Throwable) {
            return false; // 网络失败时阻断（安全优先）
        }
    }

    /**
     * 检查插件依赖兼容性
     *
     * 轻量化检查 compatibility 声明（D-06-10），返回不满足项的中文提示数组。
     *
     * @return list<string> 不满足项提示，空数组表示全部兼容
     */
    public function checkDependencies(Plugin $plugin): array
    {
        $issues        = [];
        $compatibility = $plugin->compatibility ?? [];

        if (empty($compatibility)) {
            return [];
        }

        // 当前环境版本映射（轻量化判断，不解析完整 Composer 依赖图）
        $currentVersions = $this->getCurrentEnvironmentVersions();

        foreach ($compatibility as $package => $constraint) {
            $currentVersion = $currentVersions[$package] ?? null;

            if ($currentVersion === null) {
                // 未知包，给出警告
                $issues[] = "未知依赖包 '{$package}'，请手动确认兼容性";
                continue;
            }

            try {
                if (! Semver::satisfies($currentVersion, $constraint)) {
                    $issues[] = "'{$package}' 当前版本 {$currentVersion} 不满足约束 {$constraint}";
                }
            } catch (\Throwable) {
                $issues[] = "'{$package}' 版本约束 '{$constraint}' 格式无效";
            }
        }

        return $issues;
    }

    /**
     * 获取包名对应的版本约束（从市场配置或插件声明读取）
     */
    private function getCompatibilityConstraint(string $packageName): ?string
    {
        // 从市场配置读取版本约束
        $entry = collect(config('official-market.entries', []))
            ->firstWhere('package_name', $packageName);

        if ($entry && isset($entry['version'])) {
            return (string) $entry['version'];
        }

        // 从数据库插件记录读取
        $plugin = Plugin::where('package_name', $packageName)->first();
        if ($plugin && $plugin->compatibility) {
            return $plugin->compatibility[$packageName] ?? null;
        }

        return null;
    }

    /**
     * 获取当前运行环境的包版本映射
     *
     * @return array<string, string>
     */
    private function getCurrentEnvironmentVersions(): array
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
    private function resolvePackageVersion(string $packageName): string
    {
        try {
            if (\Composer\InstalledVersions::isInstalled($packageName)) {
                return \Composer\InstalledVersions::getPrettyVersion($packageName) ?? '0.0.0';
            }
        } catch (\Throwable) {
            // 忽略
        }

        return '0.0.0';
    }

    /**
     * 向 Cache 中的初始化日志追加一行
     */
    private function appendInitLog(string $slug, string $line): void
    {
        $key      = "plugin.init.{$slug}";
        $current  = Cache::get($key, ['status' => 'running', 'logs' => []]);
        $trimmed  = trim($line);
        if ($trimmed !== '') {
            $current['logs'][] = $trimmed;
        }
        Cache::put($key, $current, 300);
    }

    /**
     * 更新 Cache 中初始化状态
     */
    private function updateCacheStatus(string $slug, string $status): void
    {
        $key     = "plugin.init.{$slug}";
        $current = Cache::get($key, ['status' => $status, 'logs' => []]);
        $current['status'] = $status;
        Cache::put($key, $current, 300);
    }

    /**
     * 读取 Cache 中累积的初始化日志文本
     */
    private function getInitLog(string $slug): string
    {
        $key     = "plugin.init.{$slug}";
        $current = Cache::get($key, ['logs' => []]);

        return implode("\n", $current['logs'] ?? []);
    }
}
