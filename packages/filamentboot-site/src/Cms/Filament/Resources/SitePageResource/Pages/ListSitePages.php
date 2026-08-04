<?php

namespace Filamentboot\FilamentbootSite\Cms\Filament\Resources\SitePageResource\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Filamentboot\FilamentbootSite\Cms\Enums\PageStatus;
use Filamentboot\FilamentbootSite\Cms\Filament\Resources\SitePageResource;
use Filamentboot\FilamentbootSite\Cms\Models\SitePage;
use Illuminate\Database\Eloquent\Builder;

/**
 * 静态页面列表页
 *
 * 按 PageStatus 分 Tab（#14）：状态一多，「哪些页面卡在待审核」这个问题
 * 靠状态列筛选器要点两下才看得到，Tab 上的计数 badge 一眼就能发现。
 */
class ListSitePages extends ListRecords
{
    protected static string $resource = SitePageResource::class;

    /**
     * @return array<int, mixed>
     */
    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }

    /**
     * 按状态分 Tab，各带计数 badge
     *
     * Tab 从 PageStatus::cases() 生成而不是手写五份：日后枚举加一个状态，
     * 这里自动跟上，不会出现「新状态的页面在任何 Tab 里都看不到」。
     *
     * @return array<string|int, Tab>
     */
    public function getTabs(): array
    {
        $tabs = [
            'all' => Tab::make('全部')
                ->badge(fn (): int => $this->countByStatus(null)),
        ];

        foreach (PageStatus::cases() as $status) {
            $tabs[$status->value] = Tab::make($status->label())
                ->badge(fn (): int => $this->countByStatus($status))
                ->badgeColor($status->color())
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('status', $status));
        }

        return $tabs;
    }

    /**
     * 统计指定状态的页面数（null 表示全部）
     *
     * 走默认的软删除作用域（不加 withTrashed）：Resource 的 getEloquentQuery()
     * 去掉了软删除过滤，但列表默认由 TrashedFilter 只显示未删除行。
     * badge 数字必须与点进去看到的行数一致，否则「待审核 3」点进去只有 1 行，
     * 用户会以为丢了数据。
     */
    protected function countByStatus(?PageStatus $status): int
    {
        $query = SitePage::query();

        if ($status !== null) {
            $query->where('status', $status);
        }

        return $query->count();
    }
}
