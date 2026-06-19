<?php

namespace FilamentAdmin\Services;

use Composer\Semver\Semver;
use FilamentAdmin\Models\Plugin;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;

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
                '插件兼容性不满足，无法启用：' . implode('；', $issues)
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
            // slug 含 vendor 前缀，避免同名包触发 UNIQUE 约束冲突
            $slug     = $meta['slug'] ?? str($packageName)->replace('/', '-')->value();
            $existing = Plugin::where('package_name', $packageName)->first();

            Plugin::updateOrCreate(
                ['package_name' => $packageName],
                [
                    'slug'               => $slug,
                    'name'               => $meta['name'] ?? $packageName,
                    'kind'               => $meta['type'] ?? 'package',
                    'plugin_class'       => $meta['plugin_class'] ?? null,
                    'settings_page_slug' => $meta['settings_page_slug'] ?? null,
                    'service_provider'   => $meta['service_provider'] ?? null,
                    'installed_version'  => $pkg['version'] ?? null,
                    'description'        => $meta['description'] ?? null,
                    'requires'           => $meta['requires'] ?? [],
                    'compatibility'      => $meta['compatibility'] ?? [],
                    'source'             => $meta['source'] ?? 'community',
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
     * 检查插件依赖兼容性
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

        $currentVersions = $this->getCurrentEnvironmentVersions();

        foreach ($compatibility as $package => $constraint) {
            $currentVersion = $currentVersions[$package] ?? null;

            if ($currentVersion === null) {
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
     * 执行数据库迁移
     */
    protected function runMigrate(string $slug): void
    {
        Artisan::call('migrate', ['--force' => true]);
        $this->appendInitLog($slug, '迁移完成：' . trim(Artisan::output()));
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

        if (! is_subclass_of($serviceProvider, \Illuminate\Support\ServiceProvider::class)) {
            $this->appendInitLog($slug, "service_provider '{$serviceProvider}' 不是有效的 ServiceProvider，跳过。");

            return;
        }

        Artisan::call('vendor:publish', [
            '--provider' => $serviceProvider,
            '--force'    => true,
        ]);
        $this->appendInitLog($slug, '资源发布完成：' . trim(Artisan::output()));
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
        $this->appendInitLog($slug, '数据填充完成：' . trim(Artisan::output()));
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
            if (\Composer\InstalledVersions::isInstalled($packageName)) {
                return \Composer\InstalledVersions::getPrettyVersion($packageName) ?? '0.0.0';
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
        $key           = "plugin.init.{$slug}";
        $current       = Cache::get($key, ['status' => $status, 'logs' => []]);
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
