<?php

namespace App\Console\Commands;

use App\Services\PluginManager;
use Illuminate\Console\Command;

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
     *
     * 委托 PluginManager::syncFromInstalled() 执行同步逻辑（WR-01 修复），
     * 避免命令与 Service 各自维护一份重复实现，消除潜在漂移风险。
     */
    public function handle(PluginManager $manager): int
    {
        $installedJson = base_path('vendor/composer/installed.json');

        if (! file_exists($installedJson)) {
            $this->warn('vendor/composer/installed.json 不存在，请先运行 composer install');

            return self::FAILURE;
        }

        $count = $manager->syncFromInstalled();
        $this->info("扫描完成，共 {$count} 个插件。");

        return self::SUCCESS;
    }
}
