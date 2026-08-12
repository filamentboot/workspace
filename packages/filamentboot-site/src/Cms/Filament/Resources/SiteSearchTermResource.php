<?php

namespace Filamentboot\FilamentbootSite\Cms\Filament\Resources;

use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Filamentboot\FilamentbootSite\Cms\Filament\Resources\SiteSearchTermResource\Pages\ListSiteSearchTerms;
use Filamentboot\FilamentbootSite\Cms\Models\SiteSearchTerm;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * 站内搜索词资源
 *
 * **纯只读**，没有表单——数据只由前台搜索行为写入。手工造一条或改掉次数
 * 等于伪造运营数据，而这张表的全部价值就是「真实发生过」。
 *
 * 默认按「零结果优先、再按次数倒序」排：**结果为 0 的词是内容缺口**，
 * 每一条都是访客明确表达了需求而站上答不上来。热词榜谁都会看，
 * 这一档才是真正指导下一批写什么的东西，所以让它默认排在最上面。
 */
class SiteSearchTermResource extends Resource
{
    /** @var class-string<SiteSearchTerm> */
    protected static ?string $model = SiteSearchTerm::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-magnifying-glass';

    protected static string|UnitEnum|null $navigationGroup = '官网管理';

    protected static ?int $navigationSort = 70;

    protected static ?string $recordTitleAttribute = 'term';

    protected static ?string $modelLabel = '站内搜索词';

    protected static ?string $pluralModelLabel = '站内搜索词';

    /**
     * 列表表格定义
     */
    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('term')
                    ->label('搜索词')
                    ->searchable()
                    ->copyable(),
                TextColumn::make('hits')
                    ->label('搜索次数')
                    ->sortable()
                    ->badge()
                    ->color('info'),
                TextColumn::make('last_result_count')
                    ->label('最近结果数')
                    ->sortable()
                    ->badge()
                    ->color(fn (int $state): string => $state === 0 ? 'danger' : 'gray')
                    ->tooltip('为 0 说明访客搜了但站上没有对应内容，这是内容缺口'),
                TextColumn::make('last_searched_at')
                    ->label('最近搜索')
                    ->dateTime('Y-m-d H:i')
                    ->sortable(),
            ])
            ->filters([
                Filter::make('without_results')
                    ->label('只看零结果')
                    ->query(fn (Builder $query): Builder => $query->where('last_result_count', 0)),
            ])
            ->recordActions([
                // 只留删除：用来清压测与爬虫留下的噪声词
                DeleteAction::make(),
            ])
            // 零结果优先，同档内按热度
            ->defaultSort(fn (Builder $query): Builder => $query
                ->orderByRaw('last_result_count = 0 desc')
                ->orderByDesc('hits'));
    }

    /**
     * 路由页面映射
     *
     * 只有列表页：没有新建，也没有编辑。
     *
     * @return array<string, mixed>
     */
    public static function getPages(): array
    {
        return [
            'index' => ListSiteSearchTerms::route('/'),
        ];
    }
}
