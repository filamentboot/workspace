<?php

namespace Filamentboot\FilamentbootSite\Modules\News\Filament;

use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filamentboot\FilamentbootSite\Modules\News\Filament\NewsCategoryResource\Pages\CreateNewsCategory;
use Filamentboot\FilamentbootSite\Modules\News\Filament\NewsCategoryResource\Pages\EditNewsCategory;
use Filamentboot\FilamentbootSite\Modules\News\Filament\NewsCategoryResource\Pages\ListNewsCategories;
use Filamentboot\FilamentbootSite\Modules\News\Models\NewsCategory;
use UnitEnum;

/**
 * 资讯分类后台资源
 *
 * 案例/方案/产品三类分类目前只能靠 seeder 维护，资讯分类是第一个开放后台管理的：
 * 资讯是持续更新的内容，栏目会随运营调整，不该每次都改代码。
 *
 * 表单不含 parent_id：模型层预留了该字段但首版前台按扁平栏目渲染，
 * 放出一个前台不生效的层级选择器只会误导编辑。
 */
class NewsCategoryResource extends Resource
{
    /** @var class-string<NewsCategory> */
    protected static ?string $model = NewsCategory::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static string|UnitEnum|null $navigationGroup = '官网管理';

    protected static ?int $navigationSort = 36;

    protected static ?string $modelLabel = '资讯分类';

    protected static ?string $pluralModelLabel = '资讯分类';

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
                TextColumn::make('articles_count')
                    ->label('文章数')
                    ->counts('articles'),
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
            'index'  => ListNewsCategories::route('/'),
            'create' => CreateNewsCategory::route('/create'),
            'edit'   => EditNewsCategory::route('/{record}/edit'),
        ];
    }
}
