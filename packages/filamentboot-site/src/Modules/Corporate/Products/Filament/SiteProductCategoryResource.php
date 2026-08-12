<?php

namespace Filamentboot\FilamentbootSite\Modules\Corporate\Products\Filament;

use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filamentboot\FilamentbootSite\Modules\Corporate\Products\Filament\SiteProductCategoryResource\Pages\CreateSiteProductCategory;
use Filamentboot\FilamentbootSite\Modules\Corporate\Products\Filament\SiteProductCategoryResource\Pages\EditSiteProductCategory;
use Filamentboot\FilamentbootSite\Modules\Corporate\Products\Filament\SiteProductCategoryResource\Pages\ListSiteProductCategories;
use Filamentboot\FilamentbootSite\Modules\Corporate\Products\Models\SiteProductCategory;
use UnitEnum;

/**
 * 产品分类后台资源
 *
 * 3.5 期 A 段补的，与 SiteCaseCategoryResource 同一批。此前产品分类**只能靠
 * seeder 维护**：`SiteProductResource` 的分类下拉里只列得出库里已有的那三条，
 * 运营想新建一个品类得改代码重播种子。`NewsCategoryResource` 早就开了后台管理，
 * 同样的东西这边一直没有——不是设计取舍，是漏了。
 *
 * 表单不含 parent_id：模型层预留了该字段但前台按扁平分类渲染，
 * 放出一个前台不生效的层级选择器只会误导编辑（与资讯分类同一口径）。
 *
 * ⚠️ 产品分类目前在**前台不可见也不可筛**——它唯一的作用是给
 * `Cms\Services\RelatedContent` 当相关性维度。要不要在产品列表页也上分类筛选，
 * 记在 3.5 期的对应性清单里、归四期第 1 档。
 */
class SiteProductCategoryResource extends Resource
{
    /** @var class-string<SiteProductCategory> */
    protected static ?string $model = SiteProductCategory::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static string|UnitEnum|null $navigationGroup = '官网管理';

    protected static ?int $navigationSort = 26;

    protected static ?string $modelLabel = '产品分类';

    protected static ?string $pluralModelLabel = '产品分类';

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
                TextColumn::make('products_count')
                    ->label('产品数')
                    ->counts('products'),
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
            'index'  => ListSiteProductCategories::route('/'),
            'create' => CreateSiteProductCategory::route('/create'),
            'edit'   => EditSiteProductCategory::route('/{record}/edit'),
        ];
    }
}
