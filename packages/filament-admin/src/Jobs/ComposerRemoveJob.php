<?php

namespace FilamentAdmin\Jobs;

use FilamentAdmin\Models\Plugin;
use FilamentAdmin\Services\PluginManager;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * 后台执行 composer remove 卸载插件
 *
 * 卸载顺序：disable → composer remove → 删 plugins 记录 → optimize:clear（Pitfall 5）。
 * dropTables 默认 false，只有显式传 true 时才删除插件自建表（D-12-14）。
 * Job timeout(600) > Process timeout(300)，防止 worker 超时（Pitfall 1）。
 */
class ComposerRemoveJob implements ShouldQueue
{
    use Queueable;

    /** @var int Laravel Job 超时（秒）— 必须 > Process timeout(300) */
    public int $timeout = 600;

    /**
     * @param  int  $pluginId  plugins 表主键
     * @param  string  $packageName  composer 包名（vendor/package 格式）
     * @param  bool  $dropTables  是否同时删除插件自建表（默认 false，D-12-14）
     */
    public function __construct(
        public readonly int $pluginId,
        public readonly string $packageName = '',
        public readonly bool $dropTables = false,
    ) {}

    /**
     * 执行卸载（委托给 PluginManager::runComposerRemove）
     */
    public function handle(PluginManager $manager): void
    {
        $plugin = Plugin::findOrFail($this->pluginId);
        $manager->runComposerRemove($plugin, $this->packageName, $this->dropTables);
    }
}
