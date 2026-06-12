<?php

namespace App\Console\Commands;

use App\Models\Plugin;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

/**
 * 扫描已安装插件命令
 *
 * 遍历 vendor/composer/installed.json，将带有 extra.filament-admin 声明的包
 * 同步写入 plugins 表（updateOrCreate，保留 is_enabled/config_overrides）。
 * （D-06-14 声明/状态分离，plugin:scan 为唯一同步入口）
 */
class ScanPlugins extends Command
{
    /** @var string */
    protected $signature = 'plugin:scan';

    /** @var string */
    protected $description = '扫描 vendor 目录中已安装的 filament-admin 插件并同步到数据库';

    /**
     * 执行命令
     */
    public function handle(): int
    {
        $installedJson = base_path('vendor/composer/installed.json');

        if (! file_exists($installedJson)) {
            $this->warn('vendor/composer/installed.json 不存在，请先运行 composer install');

            return self::FAILURE;
        }

        /** @var array{packages: array<int, array<string, mixed>>} $data */
        $data  = json_decode(file_get_contents($installedJson), true) ?? [];
        $count = 0;

        foreach ($data['packages'] ?? [] as $pkg) {
            /** @var array<string, mixed>|null $meta */
            $meta = $pkg['extra']['filament-admin'] ?? null;

            if ($meta === null) {
                // 无 extra.filament-admin 声明，跳过
                continue;
            }

            $packageName = $pkg['name'];
            // slug 取 extra.filament-admin.slug，或降级为包名最后一段
            $slug = $meta['slug'] ?? str($packageName)->afterLast('/')->value();

            // 预查询已有记录，用于保留 installed_at（幂等：首次写入 now()，重复扫描保留原值）
            // CR-01 修复：updateOrCreate 属性数组不可含 Closure，否则 preg_match 收到 Closure 抛 TypeError
            $existing = Plugin::where('package_name', $packageName)->first();

            // updateOrCreate 第二参数不含 is_enabled / config_overrides，保留运行时状态
            Plugin::updateOrCreate(
                ['package_name' => $packageName],
                [
                    'slug'              => $slug,
                    'name'              => $meta['name'] ?? $packageName,
                    'kind'              => $meta['type'] ?? 'package',
                    'plugin_class'      => $meta['plugin_class'] ?? null,
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
            $version = $pkg['version'] ?? 'unknown';
            $this->line("  [scan] {$packageName} ({$version})");
        }

        // 清除 Panel 插件列表缓存，确保启停状态实时同步
        Cache::forget('plugins.enabled_list');
        $this->info("扫描完成，共 {$count} 个插件。");

        return self::SUCCESS;
    }
}
