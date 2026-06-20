<?php

namespace App\Filament\Pages\Marketplace;

use App\Services\MarketplaceService;
use App\Services\PackagistService;
use BackedEnum;
use Filament\Pages\Page;
use FilamentAdmin\Models\Plugin;
use FilamentAdmin\Services\EnvironmentChecker;
use FilamentAdmin\Services\PluginCompatibility;
use UnitEnum;

/**
 * 插件市场浏览页（三标签视图）
 *
 * 三标签：官方市场 / 社区插件 / 已安装
 * mount() 调 MarketplaceService::fetchIndex 加载官方条目；
 * loadCommunity() 调 PackagistService 检索社区插件并映射兼容性；
 * checkInstallStatus() 供 wire:poll 轮询安装进度。
 */
class MarketplacePage extends Page
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-shopping-bag';

    protected static ?string $navigationLabel = '插件市场';

    protected static string|UnitEnum|null $navigationGroup = '插件市场';

    protected static ?string $slug = 'marketplace';

    protected string $view = 'filament.pages.marketplace';

    /** @var string 当前活跃标签：official | community | installed */
    public string $activeTab = 'official';

    /** @var array<int, array<string, mixed>> 官方市场条目 */
    public array $entries = [];

    /** @var int 社区插件分页页码 */
    public int $communityPage = 1;

    /** @var array<int, array<string, mixed>> 社区插件检索结果（含兼容性） */
    public array $communityResults = [];

    /** @var bool|null 环境自检结果（null=尚未检测） */
    public ?bool $envCheckOk = null;

    /** @var string|null Composer 可执行路径（自检通过时返回） */
    public ?string $composerPath = null;

    /** @var list<string> 自检失败时的问题描述 */
    public array $envCheckIssues = [];

    /** @var string|null 当前正在轮询的插件 init_status */
    public ?string $installStatus = null;

    /** @var list<string> 当前正在轮询的插件 init_log 行 */
    public array $installLogs = [];

    /** @var int|null 正在监控安装进度的插件 ID */
    public ?int $pollingPluginId = null;

    /**
     * 页面挂载时拉取官方市场清单 + 执行环境自检
     */
    public function mount(): void
    {
        $this->entries = app(MarketplaceService::class)->fetchIndex();
        $this->runEnvCheck();
    }

    /**
     * 切换标签并按需加载数据
     */
    public function switchTab(string $tab): void
    {
        $this->activeTab = $tab;
        if ($tab === 'community' && empty($this->communityResults)) {
            $this->loadCommunity();
        }
    }

    /**
     * 加载社区插件列表并映射三态兼容性
     *
     * 调 PackagistService::searchFilamentPlugins()，再用 PackagistService::getPackageConstraint()
     * + PluginCompatibility::checkFilamentCompatibility() 生成 compatibility_status。
     */
    public function loadCommunity(): void
    {
        $packagist   = app(PackagistService::class);
        $compat      = app(PluginCompatibility::class);
        $raw         = $packagist->searchFilamentPlugins($this->communityPage);
        $results     = $raw['results'] ?? [];

        $this->communityResults = array_map(
            fn (array $item) => $this->mapCommunityResult($item, $packagist, $compat),
            $results
        );
    }

    /**
     * 轮询目标插件的 init_status / init_log（wire:poll.2000ms 钩子）
     *
     * 当 pollingPluginId 为 null 时跳过（无活跃安装任务）。
     */
    public function checkInstallStatus(): void
    {
        if ($this->pollingPluginId === null) {
            return;
        }

        $plugin = Plugin::find($this->pollingPluginId);
        if ($plugin === null) {
            $this->pollingPluginId = null;

            return;
        }

        $this->installStatus = $plugin->init_status;
        $logText             = $plugin->init_log ?? '';
        $this->installLogs   = $logText !== '' ? explode("\n", $logText) : [];

        if (in_array($plugin->init_status, ['done', 'failed'], true)) {
            $this->pollingPluginId = null;
        }
    }

    /**
     * 执行环境自检并填充 envCheckOk / composerPath / envCheckIssues
     */
    protected function runEnvCheck(): void
    {
        $result               = app(EnvironmentChecker::class)->check();
        $this->envCheckOk     = $result['ok'];
        $this->composerPath   = $result['composer_path'];
        $this->envCheckIssues = $result['issues'];
    }

    /**
     * 将 Packagist 原始结果映射为含兼容性状态的卡片数组（≤30 行 helper）
     *
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>
     */
    protected function mapCommunityResult(
        array $item,
        PackagistService $packagist,
        PluginCompatibility $compat
    ): array {
        $constraint          = $packagist->getPackageConstraint($item['name'] ?? '');
        $compatibilityStatus = $compat->checkFilamentCompatibility($constraint);

        return [
            'name'                 => $item['name'] ?? '',
            'description'          => $item['description'] ?? '',
            'url'                  => $item['url'] ?? '',
            'repository'           => $item['repository'] ?? '',
            'downloads'            => $item['downloads'] ?? 0,
            'favers'               => $item['favers'] ?? 0,
            'source'               => 'community',
            'filament_constraint'  => $constraint,
            'compatibility_status' => $compatibilityStatus,
        ];
    }
}
