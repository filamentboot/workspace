<?php

namespace Filamentboot\FilamentbootSite\Modules\Corporate\Cases\Filament;

use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\RichEditor as RichEditorField;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\SpatieMediaLibraryImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Filamentboot\FilamentbootSite\Cms\Enums\PageStatus;
use Filamentboot\FilamentbootSite\Cms\Filament\RelationManagers\RevisionsRelationManager;
use Filamentboot\FilamentbootSite\Modules\Corporate\Cases\Enums\CaseStyle;
use Filamentboot\FilamentbootSite\Modules\Corporate\Cases\Enums\HouseType;
use Filamentboot\FilamentbootSite\Modules\Corporate\Cases\Filament\SiteCaseResource\Pages\CreateSiteCase;
use Filamentboot\FilamentbootSite\Modules\Corporate\Cases\Filament\SiteCaseResource\Pages\EditSiteCase;
use Filamentboot\FilamentbootSite\Modules\Corporate\Cases\Filament\SiteCaseResource\Pages\ListSiteCases;
use Filamentboot\FilamentbootSite\Modules\Corporate\Cases\Models\SiteCase;
use Filamentboot\FilamentbootSite\SiteServiceProvider;
use Filamentboot\Settings\UploadSettings;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use UnitEnum;

/**
 * 装修案例后台资源
 *
 * 提供案例 CRUD，含内容 Tab（基本信息/内容/SEO/图片）、
 * 分类、标签、发布状态、置顶、Phase 9 富文本编辑器与 Spatie 媒体库。
 *
 * @extends \Filament\Resources\Resource<SiteCase>
 */
class SiteCaseResource extends Resource
{
    /** @var class-string<SiteCase> */
    protected static ?string $model = SiteCase::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-home-modern';

    protected static string|UnitEnum|null $navigationGroup = '官网管理';

    protected static ?int $navigationSort = 10;

    /**
     * 「案例」是两套模板都用的内容类型，「装修案例」是 decoration 专属叫法
     * （六期批次 9 实测发现：这个标签不分主题、后台侧边栏与页面标题全局生效，
     * software 模板管理员天天看到错误标签）。按 active_theme 动态取，而不是
     * 静态属性——参照 Filament `HasLabels` trait 的实现，覆写方法优先级高于属性。
     */
    public static function getModelLabel(): string
    {
        return SiteServiceProvider::resolveActiveTheme() === 'software' ? '案例' : '装修案例';
    }

    public static function getPluralModelLabel(): string
    {
        return static::getModelLabel();
    }

    /**
     * 表单定义（内容 Tab + SEO + 图片 + 分类标签 + 发布置顶）
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
                        Select::make('style')
                            ->label('装修风格')
                            ->options(
                                collect(CaseStyle::cases())
                                    ->mapWithKeys(fn (CaseStyle $s): array => [$s->value => $s->label()])
                                    ->all()
                            ),
                        Select::make('house_type')
                            ->label('户型')
                            ->options(
                                collect(HouseType::cases())
                                    ->mapWithKeys(fn (HouseType $h): array => [$h->value => $h->label()])
                                    ->all()
                            ),
                        TextInput::make('area')
                            ->label('面积（㎡）')
                            ->maxLength(50),
                        TextInput::make('budget_range')
                            ->label('预算范围')
                            ->maxLength(100),
                        Textarea::make('smart_features')
                            ->label('智能亮点')
                            ->rows(3)
                            ->helperText('详情页正文之前会渲染成一组「这套做了什么」。顿号、逗号或换行分隔都行，留空则整块不显示'),
                        Toggle::make('is_featured')
                            ->label('置顶/精选'),
                        TextInput::make('sort')
                            ->label('排序权重')
                            ->numeric()
                            ->default(0)
                            ->helperText('列表页先按它升序排，同值再按发布时间倒序。想把某条钉在最前面就填负数'),
                        Select::make('status')
                            ->label('发布状态')
                            ->options(PageStatus::options())
                            ->default(PageStatus::DRAFT->value)
                            ->required()
                            ->native(false)
                            ->live()
                            ->helperText('选「定时发布」并填写下方发布时间，到点后前台自动可见，无需队列或定时任务'),
                        DateTimePicker::make('published_at')
                            ->label('发布时间（留空=草稿）')
                            ->required(fn (Get $get): bool => $get('status') === PageStatus::SCHEDULED->value),
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
                            ->label('正文'),
                        Section::make('业主见证')
                            ->description('姓名与引言缺一不可，只填其一前台不渲染见证卡片')
                            ->collapsed()
                            ->schema([
                                TextInput::make('customer_name')
                                    ->label('业主称呼')
                                    ->placeholder('张先生')
                                    ->maxLength(50),
                                TextInput::make('customer_meta')
                                    ->label('身份备注')
                                    ->placeholder('万科城市之光 · 入住 8 个月')
                                    ->maxLength(255),
                                Textarea::make('customer_quote')
                                    ->label('业主原话')
                                    ->rows(4)
                                    ->maxLength(500),
                                SpatieMediaLibraryFileUpload::make('customer_avatar')
                                    ->label('业主头像')
                                    ->collection('avatar')
                                    ->disk($defaultDisk)
                                    ->image()
                                    ->imageEditor()
                                    ->avatar()
                                    ->helperText('留空时前台用称呼首字生成占位圆标'),
                            ]),
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
                            ->imageEditor()
                            ->imageEditorAspectRatios(['4:3'])
                            ->helperText('建议不小于 1200×900，比例 4:3。裁剪框内就是最终构图，系统只做等比缩放、不再自动裁切；详情页顶部大图会另裁一版 1.91:1，主体请留在画面中部。'),
                        SpatieMediaLibraryFileUpload::make('gallery')
                            ->label('图集')
                            ->collection('gallery')
                            ->disk($defaultDisk)
                            ->multiple()
                            ->image()
                            ->imageEditor()
                            ->imageEditorAspectRatios(['4:3'])
                            ->helperText('建议不小于 800×600，比例 4:3。'),
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
                TextColumn::make('status')
                    ->label('状态')
                    ->badge()
                    ->formatStateUsing(fn (mixed $state): string => $state instanceof PageStatus
                        ? $state->label()
                        : (string) (PageStatus::tryFrom((string) $state)?->label() ?? $state))
                    ->color(fn (mixed $state): string => $state instanceof PageStatus
                        ? $state->color()
                        : (PageStatus::tryFrom((string) $state)?->color() ?? 'gray')),
                IconColumn::make('is_featured')
                    ->label('置顶')
                    ->boolean()
                    ->trueColor('warning')
                    ->falseColor('gray'),
                TextColumn::make('published_at')
                    ->label('发布时间')
                    ->dateTime('Y-m-d H:i')
                    ->sortable()
                    ->placeholder('草稿'),
            ])
            ->filters([
                TrashedFilter::make(),
                SelectFilter::make('status')
                    ->label('状态')
                    ->options(PageStatus::options()),
                SelectFilter::make('style')
                    ->label('风格')
                    ->options(
                        collect(CaseStyle::cases())
                            ->mapWithKeys(fn (CaseStyle $s): array => [$s->value => $s->label()])
                            ->all()
                    ),
                SelectFilter::make('house_type')
                    ->label('户型')
                    ->options(
                        collect(HouseType::cases())
                            ->mapWithKeys(fn (HouseType $h): array => [$h->value => $h->label()])
                            ->all()
                    ),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
                RestoreAction::make(),
            ])
            ->defaultSort('published_at', 'desc');
    }

    /**
     * 覆盖 Eloquent 查询，去除软删除作用域使已删除记录在 TrashedFilter 下可见
     *
     * @return Builder<SiteCase>
     */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([SoftDeletingScope::class]);
    }

    /**
     * 关系管理器（批次 1.5c 版本历史）
     *
     * @return array<int, mixed>
     */
    public static function getRelations(): array
    {
        return [
            RevisionsRelationManager::class,
        ];
    }

    /**
     * 路由页面映射
     *
     * @return array<string, mixed>
     */
    public static function getPages(): array
    {
        return [
            'index' => ListSiteCases::route('/'),
            'create' => CreateSiteCase::route('/create'),
            'edit' => EditSiteCase::route('/{record}/edit'),
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
