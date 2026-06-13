<?php

namespace LaravelStack\FilamentAdminSite\Filament\Resources;

use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Forms\Components\DateTimePicker;
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
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use FilamentAdmin\Settings\UploadSettings;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use LaravelStack\FilamentAdminRichEditor\Forms\RichEditorField;
use LaravelStack\FilamentAdminSite\Enums\CaseStyle;
use LaravelStack\FilamentAdminSite\Enums\HouseType;
use LaravelStack\FilamentAdminSite\Filament\Resources\SiteCaseResource\Pages\CreateSiteCase;
use LaravelStack\FilamentAdminSite\Filament\Resources\SiteCaseResource\Pages\EditSiteCase;
use LaravelStack\FilamentAdminSite\Filament\Resources\SiteCaseResource\Pages\ListSiteCases;
use LaravelStack\FilamentAdminSite\Models\SiteCase;
use UnitEnum;

/**
 * 装修案例后台资源
 *
 * 提供案例 CRUD，含双语 Tab（中文/English/SEO/图片/基本信息）、
 * 分类、标签、发布状态、置顶、Phase 9 富文本编辑器与 Spatie 媒体库。
 */
class SiteCaseResource extends Resource
{
    /** @var class-string<SiteCase> */
    protected static ?string $model = SiteCase::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-home-modern';

    protected static string|UnitEnum|null $navigationGroup = '官网管理';

    protected static ?int $navigationSort = 10;

    protected static ?string $modelLabel = '装修案例';

    protected static ?string $pluralModelLabel = '装修案例';

    /**
     * 表单定义（双语 Tab + SEO + 图片 + 分类标签 + 发布置顶）
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
                            ->rows(3),
                        Toggle::make('is_featured')
                            ->label('置顶/精选'),
                        DateTimePicker::make('published_at')
                            ->label('发布时间（留空=草稿）'),
                    ]),
                    Tab::make('中文')->schema([
                        TextInput::make('title_zh')
                            ->label('标题（中文）')
                            ->required()
                            ->maxLength(255),
                        Textarea::make('description_zh')
                            ->label('描述（中文）')
                            ->rows(3),
                        RichEditorField::make('content_zh')
                            ->label('正文（中文）'),
                    ]),
                    Tab::make('English')->schema([
                        TextInput::make('title_en')
                            ->label('Title (English)')
                            ->maxLength(255),
                        Textarea::make('description_en')
                            ->label('Description (English)')
                            ->rows(3),
                        RichEditorField::make('content_en')
                            ->label('Content (English)'),
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
                            ->image(),
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
                    ->label('标题（中文）')
                    ->searchable()
                    ->limit(30),
                TextColumn::make('category.name_zh')
                    ->label('分类')
                    ->default('-'),
                IconColumn::make('is_published')
                    ->label('状态')
                    ->getStateUsing(fn (SiteCase $record): bool => $record->published_at !== null && $record->published_at <= now())
                    ->boolean()
                    ->trueColor('success')
                    ->falseColor('gray')
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-clock'),
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
                \Filament\Actions\EditAction::make(),
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
     * 路由页面映射
     *
     * @return array<string, mixed>
     */
    public static function getPages(): array
    {
        return [
            'index'  => ListSiteCases::route('/'),
            'create' => CreateSiteCase::route('/create'),
            'edit'   => EditSiteCase::route('/{record}/edit'),
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
