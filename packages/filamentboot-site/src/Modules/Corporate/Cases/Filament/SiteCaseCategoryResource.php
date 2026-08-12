<?php

namespace Filamentboot\FilamentbootSite\Modules\Corporate\Cases\Filament;

use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filamentboot\FilamentbootSite\Modules\Corporate\Cases\Filament\SiteCaseCategoryResource\Pages\CreateSiteCaseCategory;
use Filamentboot\FilamentbootSite\Modules\Corporate\Cases\Filament\SiteCaseCategoryResource\Pages\EditSiteCaseCategory;
use Filamentboot\FilamentbootSite\Modules\Corporate\Cases\Filament\SiteCaseCategoryResource\Pages\ListSiteCaseCategories;
use Filamentboot\FilamentbootSite\Modules\Corporate\Cases\Models\SiteCaseCategory;
use UnitEnum;

/**
 * 案例分类后台资源
 *
 * 3.5 期 A 段补的。此前案例分类**只能靠 seeder 维护**：`SiteCaseResource` 的
 * 分类下拉里只列得出库里已有的几条，运营想新建一个分类得改代码重播种子。
 * `NewsCategoryResource` 早就开了后台管理，同样的东西案例这边一直没有——
 * 不是设计取舍，是漏了。
 *
 * 表单不含 parent_id：模型层预留了该字段但前台按扁平分类渲染，
 * 放出一个前台不生效的层级选择器只会误导编辑（与资讯分类同一口径）。
 *
 * ⚠️ 案例分类目前在**前台不可见也不可筛**——它唯一的作用是给
 * `Cms\Services\RelatedContent` 当相关性维度。要不要给案例列表页也上分类筛选
 * 是产品决定，记在 3.5 期的对应性清单里、归四期第 1 档。
 */
class SiteCaseCategoryResource extends Resource
{
    /** @var class-string<SiteCaseCategory> */
    protected static ?string $model = SiteCaseCategory::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static string|UnitEnum|null $navigationGroup = '官网管理';

    protected static ?int $navigationSort = 12;

    protected static ?string $modelLabel = '案例分类';

    protected static ?string $pluralModelLabel = '案例分类';

    /**
     * 表单定义（名称 + slug + 排序）
     */
    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name_zh')
                ->label('分类名称')
                ->required()
                ->maxLength(100),
            TextInput::make('slug')
                ->label('URL Slug')
                ->required()
                ->unique(ignoreRecord: true)
                ->rules(['alpha_dash'])
                ->maxLength(255),
            TextInput::make('sort')
                ->label('排序')
                ->numeric()
                ->default(0)
                ->helperText('数字越小越靠前'),
        ]);
    }

    /**
     * 列表表格定义
     */
    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name_zh')
                    ->label('分类名称')
                    ->searchable(),
                TextColumn::make('slug')
                    ->label('Slug')
                    ->searchable(),
                TextColumn::make('cases_count')
                    ->label('案例数')
                    ->counts('cases'),
                TextColumn::make('sort')
                    ->label('排序')
                    ->sortable(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->defaultSort('sort', 'asc');
    }

    /**
     * 路由页面映射
     *
     * @return array<string, mixed>
     */
    public static function getPages(): array
    {
        return [
            'index'  => ListSiteCaseCategories::route('/'),
            'create' => CreateSiteCaseCategory::route('/create'),
            'edit'   => EditSiteCaseCategory::route('/{record}/edit'),
        ];
    }
}
