<?php

namespace App\Filament\Resources\PluginResource\Pages;

use App\Filament\Resources\PluginResource;
use App\Services\PluginManager;
use Filament\Actions\Action;
use Filament\Resources\Pages\ViewRecord;
use Filamentboot\Models\Plugin;
use Illuminate\Support\Facades\Cache;

/**
 * 插件详情页（含初始化进度轮询）
 *
 * 方案型插件（kind=solution_plugin）可触发「初始化」操作，
 * wire:poll.2000ms 实时轮询 Cache 进度日志。
 * 初始化失败时显示「重试初始化」按钮（整体幂等重跑）。
 *
 * @property-read Plugin $record
 */
class ViewPlugin extends ViewRecord
{
    protected static string $resource = PluginResource::class;

    protected string $view = 'filament.resources.plugin-resource.pages.view-plugin';

    /** @var array<int, string> 初始化日志行列表 */
    public array $initLogs = [];

    /** @var string 初始化状态：idle | running | done | failed */
    public string $initStatus = 'idle';

    /**
     * wire:poll.2000ms 调用此方法刷新进度
     *
     * 从 Cache 读取 plugin.init.{slug} 回填 initLogs/initStatus。
     */
    public function refreshInitProgress(): void
    {
        $cache = Cache::get('plugin.init.'.$this->record->slug);

        if ($cache) {
            $this->initLogs   = $cache['logs'] ?? [];
            $this->initStatus = $cache['status'] ?? 'running';
        }
    }

    /**
     * 执行同步初始化（调 PluginManager::initialize，OQ1 同步语义）
     *
     * 初始化完成后立即读取最终状态。
     * 注意：此方法通过 wire:click 直接暴露为 Livewire 端点，必须显式授权，
     * 不能依赖 Filament Action 的 ->authorize() 守卫（CR-02 修复）。
     */
    public function initialize(): void
    {
        // 显式授权：走 PluginPolicy::initialize()，防止绕过 Action 权限检查
        $this->authorize('initialize', $this->record);

        app(PluginManager::class)->initialize($this->record);
        $this->refreshInitProgress();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('initialize')
                ->label('初始化')
                ->action('initialize')
                ->requiresConfirmation()
                ->authorize('initialize_plugin')
                ->visible(fn (): bool => $this->record->kind === 'solution_plugin'),
        ];
    }
}
