<?php

namespace Filamentboot\FilamentbootSite\Filament\Resources;

use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filamentboot\FilamentbootSite\Filament\Resources\SiteTagResource\Pages\CreateSiteTag;
use Filamentboot\FilamentbootSite\Filament\Resources\SiteTagResource\Pages\EditSiteTag;
use Filamentboot\FilamentbootSite\Filament\Resources\SiteTagResource\Pages\ListSiteTags;
use Filamentboot\FilamentbootSite\Models\SiteTag;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * 标签后台资源
 *
 * 3.5 期 A 段补的。标签比分类更该有后台入口：它在**前台是可见的**——五类详情页
 * 都渲染标签链接，`/tags/{slug}` 还是一批独立的聚合页（三期批次 3 上的）。
 * 也就是说标签直接决定了一批公开 URL 与站内互链结构，而在此之前**运营只能在
 * 内容表单的下拉里选已有的，建不了新的、改不了名、删不掉**。
 *
 * 改名要当心：`slug` 就是聚合页地址（`/tags/smart-home`）。改了它，
 * 旧地址立刻 404，而站内其它页面上的标签链接会指向新地址——外部已收录的链接断掉。
 * 所以表单里对 slug 给了明确提示。
 */
class SiteTagResource extends Resource
{
    /** @var class-string<SiteTag> */
    protected static ?string $model = SiteTag::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-tag';

    protected static string|UnitEnum|null $navigationGroup = '官网管理';

    protected static ?int $navigationSort = 38;

    protected static ?string $modelLabel = '标签';

    protected static ?string $pluralModelLabel = '标签';

    /**
     * 表单定义（名称 + slug）
     */
    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name_zh')
                ->label('标签名称')
                ->required()
                ->maxLength(100),
            TextInput::make('slug')
                ->label('URL Slug')
                ->required()
                ->unique(ignoreRecord: true)
                ->rules(['alpha_dash'])
                ->maxLength(255)
                ->helperText('聚合页地址就是它：/tags/这个值。⚠️ 改了旧地址立刻 404，'
                    .'外部已收录的链接会断——除非确有必要，建好之后别再动'),
        ]);
    }

    /**
     * 列表表格定义
     *
     * 「被引用」是五类内容的合计。分成五列更精确，但标签表本身只有十几行，
     * 列多了反而看不出哪个标签是空的——而空标签正是这张表要暴露的东西：
     * 它会在 /tags 下留一个没有内容的聚合页。
     */
    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->withCount([
                'cases', 'solutions', 'packages', 'products', 'news',
            ]))
            ->columns([
                TextColumn::make('name_zh')
                    ->label('标签名称')
                    ->searchable(),
                TextColumn::make('slug')
                    ->label('Slug')
                    ->searchable(),
                TextColumn::make('usage')
                    ->label('被引用')
                    ->state(fn (SiteTag $record): int => (int) $record->cases_count
                        + (int) $record->solutions_count
                        + (int) $record->packages_count
                        + (int) $record->products_count
                        + (int) $record->news_count)
                    ->badge()
                    ->color(fn (int $state): string => $state > 0 ? 'success' : 'gray')
                    ->description('0 表示聚合页是空的'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->defaultSort('name_zh', 'asc');
    }

    /**
     * 路由页面映射
     *
     * @return array<string, mixed>
     */
    public static function getPages(): array
    {
        return [
            'index'  => ListSiteTags::route('/'),
            'create' => CreateSiteTag::route('/create'),
            'edit'   => EditSiteTag::route('/{record}/edit'),
        ];
    }
}
