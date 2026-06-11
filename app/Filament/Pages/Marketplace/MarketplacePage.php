<?php

namespace App\Filament\Pages\Marketplace;

use App\Services\MarketplaceService;
use BackedEnum;
use Filament\Pages\Page;
use UnitEnum;

/**
 * 官方市场浏览页
 *
 * 只读浏览远程 index.json，不写 MySQL（D-06-06）。
 * mount() 调 MarketplaceService::fetchIndex，展示远程清单 + composer require 复制命令。
 */
class MarketplacePage extends Page
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-shopping-bag';

    protected static ?string $navigationLabel = '浏览官方市场';

    protected static string|UnitEnum|null $navigationGroup = '插件市场';

    protected static ?string $slug = 'marketplace';

    protected string $view = 'filament.pages.marketplace';

    /** @var array<int, array<string, mixed>> 市场清单条目 */
    public array $entries = [];

    /**
     * 页面挂载时拉取市场清单（HTTP 缓存，不写库）
     */
    public function mount(): void
    {
        $this->entries = app(MarketplaceService::class)->fetchIndex();
    }
}
