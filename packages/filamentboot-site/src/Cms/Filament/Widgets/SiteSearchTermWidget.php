<?php

namespace Filamentboot\FilamentbootSite\Cms\Filament\Widgets;

use Filament\Facades\Filament;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Filamentboot\FilamentbootSite\Cms\Models\SiteSearchTerm;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;

/**
 * 站内搜索缺口看板
 *
 * 与 SiteSearchTermResource 的列表页看的是同一张表，摆在仪表盘上是因为
 * **这类信息不主动看就等于没有**。谁也不会每周想起来点进「站内搜索词」，
 * 而这里每一条零结果都是访客明确说出了需求、站上答不上来。
 *
 * 排序与资源列表一致：**零结果优先，再按次数**。热词榜排在后面不是因为它
 * 不重要，而是因为热词说明你已经做对了，不需要谁去处理。
 *
 * 只出前几条，看全部去资源列表——看板要能被一眼扫完，
 * 长列表在仪表盘上就是噪音。
 */
class SiteSearchTermWidget extends TableWidget
{
    protected static ?int $sort = 12;

    protected int|string|array $columnSpan = 'full';

    /** 榜单长度 */
    protected const TOP = 8;

    /**
     * 谁能看见这个 Widget
     *
     * 与站内搜索词资源同一道闸。表未迁移时一并隐藏。
     */
    public static function canView(): bool
    {
        $user = Filament::auth()->user();

        return $user !== null
            && Schema::hasTable('site_search_terms')
            && Gate::forUser($user)->allows('viewAny', SiteSearchTerm::class);
    }

    /**
     * 表格定义
     */
    public function table(Table $table): Table
    {
        return $table
            ->heading('站内搜索')
            ->description('零结果的排在最前——那是访客搜了、站上却没有内容的词，直接就是下一批该写什么。')
            ->query(fn (): Builder => SiteSearchTerm::query()
                ->orderByRaw('last_result_count = 0 desc')
                ->orderByDesc('hits')
                ->limit(self::TOP))
            ->columns([
                TextColumn::make('term')
                    ->label('搜索词')
                    ->wrap(),
                TextColumn::make('hits')
                    ->label('搜索次数')
                    ->badge()
                    ->color('info'),
                TextColumn::make('last_result_count')
                    ->label('最近结果数')
                    ->badge()
                    ->color(fn (int $state): string => $state === 0 ? 'danger' : 'gray')
                    ->tooltip('为 0 说明访客搜了但站上没有对应内容'),
                TextColumn::make('last_searched_at')
                    ->label('最近搜索')
                    ->dateTime('Y-m-d H:i'),
            ])
            ->paginated(false)
            ->emptyStateHeading('还没有人用过站内搜索')
            ->emptyStateDescription('访客每搜一次都会累加到这里，不记 IP、不记身份。');
    }
}
