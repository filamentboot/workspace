<?php

namespace Filamentboot\FilamentbootSite\Filament\Widgets;

use Filament\Facades\Filament;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Filamentboot\FilamentbootSite\Models\ContactMessage;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;

/**
 * 转化来源看板
 *
 * 回答一个问题：**站上这十几个转化入口，哪个真的在带线索。**
 *
 * 询盘表从一开始就记了 `source`，但此前只能在询盘列表里一条条翻，或者靠
 * 筛选器一个个来源点过去数。铺开城市页之后这件事变成了硬需求——
 * 三期的观察期结论「要不要继续铺第二批城市」只能由这张表回答，
 * 而它对应的来源有三百多个值，人肉数是不可能的。
 *
 * ## 为什么是两列计数而不是一列
 *
 * `recent` 是近 30 天，`total` 是累计。只看累计，一个早就下线的入口会永远
 * 挂在榜首；只看近 30 天，样本又太小。两列并排才看得出「这个入口是一直有效
 * 还是最近才起来」。排序按 `recent`，因为要拿它做的决定是关于**下一步**的。
 *
 * ## 聚合查询的两个讲究
 *
 * - `MIN(id) as id`：Filament 的表格要求每行有主键，而 GROUP BY 之后没有。
 *   取组内最小 id 当行标识，它只被用作 DOM key，不参与任何业务判断。
 * - `SUM(CASE WHEN ...)` 而不是两条查询或 MySQL 专有的条件计数：
 *   这条 SQL 在 MySQL 与 SQLite 上是同一套写法，测试库跑得起来。
 */
class ContactSourceWidget extends TableWidget
{
    protected static ?int $sort = 11;

    protected int|string|array $columnSpan = 'full';

    /** 近期窗口（天）。30 天是一个投放周期，比「本月」稳定 */
    protected const RECENT_DAYS = 30;

    /** 榜单长度。看的是头部，尾巴去询盘列表按来源筛 */
    protected const TOP = 12;

    /**
     * 谁能看见这个 Widget
     *
     * 与询盘资源同一道闸：看不了询盘的人也不该从看板上看出线索总量与来源分布。
     * 表未迁移时一并隐藏（同 UnreadContactMessagesWidget 的降级纪律）。
     */
    public static function canView(): bool
    {
        $user = Filament::auth()->user();

        return $user !== null
            && Schema::hasTable('site_contact_messages')
            && Gate::forUser($user)->allows('viewAny', ContactMessage::class);
    }

    /**
     * 表格定义
     */
    public function table(Table $table): Table
    {
        return $table
            ->heading('转化来源')
            ->description('按近 '.self::RECENT_DAYS.' 天的询盘条数排，只看前 '.self::TOP.' 名。要看全部请去询盘列表按来源筛。')
            ->query(fn (): Builder => $this->rankQuery())
            ->columns([
                TextColumn::make('source')
                    ->label('入口')
                    ->formatStateUsing(
                        fn (mixed $state, ContactMessage $record): string => $record->sourceLabel() ?? '-'
                    )
                    ->wrap(),
                TextColumn::make('recent')
                    ->label('近 '.self::RECENT_DAYS.' 天')
                    ->badge()
                    ->color(fn (mixed $state): string => (int) $state > 0 ? 'success' : 'gray'),
                TextColumn::make('total')
                    ->label('累计')
                    ->badge()
                    ->color('gray'),
            ])
            // 关掉分页：这是看板不是列表，翻页看排名没有意义，
            // 而且 GROUP BY 查询的分页计数要多跑一条子查询
            ->paginated(false)
            // ⚠️ 必须关。Filament 默认会在排序末尾追加一条按主键的兜底排序
            // （保证同值行的顺序稳定），而这里 GROUP BY 之后 `id` 是聚合出来的，
            // MySQL 的 only_full_group_by 直接拒绝整条语句。
            // 本表的排序键已经唯一（source 是分组键），不需要那条兜底
            ->defaultKeySort(false)
            ->emptyStateHeading('还没有带来源的询盘')
            ->emptyStateDescription('访客从任一 CTA 提交后，这里会按入口聚合出来。');
    }

    /**
     * 按来源聚合的排行查询
     *
     * @return Builder<ContactMessage>
     */
    protected function rankQuery(): Builder
    {
        return ContactMessage::query()
            ->selectRaw('MIN(id) as id, source, COUNT(*) as total')
            ->selectRaw(
                'SUM(CASE WHEN created_at >= ? THEN 1 ELSE 0 END) as recent',
                [now()->subDays(self::RECENT_DAYS)]
            )
            ->whereNotNull('source')
            ->groupBy('source')
            ->orderByDesc('recent')
            ->orderByDesc('total')
            ->limit(self::TOP);
    }
}
