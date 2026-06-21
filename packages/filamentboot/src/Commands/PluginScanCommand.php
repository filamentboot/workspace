<?php

namespace Filamentboot\Commands;

use Filamentboot\Services\PluginManager;
use Illuminate\Console\Command;

/**
 * 扫描已安装插件命令
 *
 * 遍历 vendor/composer/installed.json，将含 extra.filament-admin 声明的包
 * 同步写入 plugins 表（updateOrCreate，保留 is_enabled/config_overrides）。
 */
class PluginScanCommand extends Command
{
    /** @var string */
    protected $signature = 'plugin:scan';

    /** @var string */
    protected $description = '扫描 vendor 目录中已安装的 filament-admin 插件并同步到数据库';

    /**
     * 执行命令
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
