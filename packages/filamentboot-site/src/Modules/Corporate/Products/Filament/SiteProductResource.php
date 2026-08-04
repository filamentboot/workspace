<?php

namespace Filamentboot\FilamentbootSite\Modules\Corporate\Products\Filament;

use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreAction;
use Filament\Forms\Components\RichEditor as RichEditorField;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\SpatieMediaLibraryImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Filamentboot\FilamentbootSite\Modules\Corporate\Products\Filament\SiteProductResource\Pages\CreateSiteProduct;
use Filamentboot\FilamentbootSite\Modules\Corporate\Products\Filament\SiteProductResource\Pages\EditSiteProduct;
use Filamentboot\FilamentbootSite\Modules\Corporate\Products\Filament\SiteProductResource\Pages\ListSiteProducts;
use Filamentboot\FilamentbootSite\Modules\Corporate\Products\Models\SiteProduct;
use Filamentboot\Settings\UploadSettings;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use UnitEnum;

/**
 * 智能产品后台资源
 *
 * 提供产品 CRUD，含内容 Tab（基本信息/内容/SEO/图片）、
 * 分类、标签、is_published 布尔发布、置顶、封面图与图集（UploadSettings 磁盘）。
 *
 * 封面图与图集集中在「图片」Tab，与 SiteCaseResource 保持一致：
 * 图集是多图上传控件，放在字段密集的「基本信息」里会把那一屏撑得很长。
 *
 * @extends \Filament\Resources\Resource<SiteProduct>
 */
class SiteProductResource extends Resource
{
    /** @var class-string<SiteProduct> */
    protected static ?string $model = SiteProduct::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-cpu-chip';

    protected static string|UnitEnum|null $navigationGroup = '官网管理';

    protected static ?int $navigationSort = 30;

    protected static ?string $modelLabel = '智能产品';

    protected static ?string $pluralModelLabel = '智能产品';

    /**
     * 表单定义（内容 Tab + SEO + 分类标签 + 发布置顶 + 封面图）
     */
    public static function form(Schema $schema): Schema
    {
        $defaultDisk = static::resolveDefaultDisk();

        return $schema->components([
            Tabs::make('内容')
                ->tabs([
                    Tab::make('基本信息')->schema([
                        TextInput::make('slug')
                            ->label('URL Slug')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->rules(['alpha_dash'])
                            ->maxLength(255),
                        Select::make('category_id')
                            ->label('分类')
                            ->relationship('category', 'name_zh')
                            ->searchable()
                            ->preload(),
                        Select::make('tags')
                            ->label('标签')
                            ->relationship('tags', 'name_zh')
                            ->multiple()
                            ->preload(),
                        TextInput::make('price')
                            ->label('价格（元）')
                            ->numeric()
                            ->step(0.01),
                        TextInput::make('brand')
                            ->label('品牌')
                            ->maxLength(100),
                        Toggle::make('is_featured')
                            ->label('置顶/精选'),
                        Toggle::make('is_published')
                            ->label('已发布'),
                    ]),
                    Tab::make('内容')->schema([
                        TextInput::make('title_zh')
                            ->label('标题')
                            ->required()
                            ->maxLength(255),
                        Textarea::make('description_zh')
                            ->label('描述')
                            ->rows(3),
                        RichEditorField::make('content_zh')
                            ->label('详情正文')
                            ->helperText('产品参数、功能说明写在这里；不再单独维护结构化规格字段'),
                    ]),
                    Tab::make('SEO')->schema([
                        TextInput::make('seo_title')
                            ->label('SEO 标题')
                            ->maxLength(70),
                        Textarea::make('seo_description')
                            ->label('SEO 描述')
                            ->maxLength(160)
                            ->rows(2),
                        TextInput::make('seo_keywords')
                            ->label('SEO 关键词')
                            ->maxLength(255),
                    ]),
                    Tab::make('图片')->schema([
                        SpatieMediaLibraryFileUpload::make('cover_image')
                            ->label('封面图')
                            ->collection('cover')
                            ->disk($defaultDisk)
                            ->image()
                            ->imageEditor(),
                        SpatieMediaLibraryFileUpload::make('gallery')
                            ->label('图集')
                            ->collection('gallery')
                            ->disk($defaultDisk)
                            ->multiple()
                            ->reorderable()
                            ->image()
                            ->helperText('详情页主图轮播，可拖拽排序；第一张为轮播默认展示'),
                    ]),
                ])
                ->columnSpanFull(),
        ]);
    }

    /**
     * 列表表格定义
     */
    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                SpatieMediaLibraryImageColumn::make('cover_image')
                    ->label('封面')
                    ->collection('cover')
                    ->conversion('thumb')
                    ->circular(),
                TextColumn::make('title_zh')
                    ->label('标题')
                    ->searchable()
                    ->limit(30),
                TextColumn::make('category.name_zh')
                    ->label('分类')
                    ->default('-'),
                TextColumn::make('price')
                    ->label('价格')
                    ->money('CNY')
                    ->placeholder('-'),
                IconColumn::make('is_published')
                    ->label('已发布')
                    ->boolean()
                    ->trueColor('success')
                    ->falseColor('gray'),
                IconColumn::make('is_featured')
                    ->label('置顶')
                    ->boolean()
                    ->trueColor('warning')
                    ->falseColor('gray'),
                TextColumn::make('sort')
                    ->label('排序')
                    ->sortable(),
            ])
            ->filters([
                TrashedFilter::make(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
                RestoreAction::make(),
            ])
            ->defaultSort('sort', 'asc');
    }

    /**
     * 覆盖 Eloquent 查询，去除软删除作用域
     *
     * @return Builder<SiteProduct>
     */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([SoftDeletingScope::class]);
    }

    /**
     * 路由页面映射
     *
     * @return array<string, mixed>
     */
    public static function getPages(): array
    {
        return [
            'index'  => ListSiteProducts::route('/'),
            'create' => CreateSiteProduct::route('/create'),
            'edit'   => EditSiteProduct::route('/{record}/edit'),
        ];
    }

    /**
     * 解析默认上传磁盘（SITE-04 跨切：读 UploadSettings，降级 'public'）
     */
    protected static function resolveDefaultDisk(): string
    {
        try {
            return app(UploadSettings::class)->default_disk;
        } catch (\Throwable) {
            return 'public';
        }
    }
}
