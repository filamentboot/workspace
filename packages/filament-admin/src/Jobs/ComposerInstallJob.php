<?php

namespace FilamentAdmin\Jobs;

use FilamentAdmin\Models\Plugin;
use FilamentAdmin\Services\PluginManager;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * 后台执行 composer require 安装插件
 *
 * 通过 symfony/process 子进程执行 composer require；
 * 实时输出追加到 plugins.init_log；
 * 成功后调 PluginManager::postInstall() 完成后置生命周期。
 * Job timeout(600) > Process timeout(300)，防止 worker 超时留下僵尸进程（Pitfall 1）。
 */
class ComposerInstallJob implements ShouldQueue
{
    use Queueable;

    /** @var int Laravel Job 超时（秒）— 必须 > Process timeout(300) */
    public int $timeout = 600;

    /**
     * @param  int  $pluginId  plugins 表主键
     * @param  string  $packageName  composer 包名（vendor/package 格式，已验证）
     */
    public function __construct(
        public readonly int $pluginId,
        public readonly string $packageName,
    ) {}

    /**
     * 执行安装（委托给 PluginManager::runComposerInstall）
     */
    public function handle(PluginManager $manager): void
    {
        $plugin = Plugin::findOrFail($this->pluginId);
        $manager->runComposerInstall($plugin, $this->packageName);
    }
}
