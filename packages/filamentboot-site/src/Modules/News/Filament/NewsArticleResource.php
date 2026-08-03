<?php

namespace Filamentboot\FilamentbootSite\Modules\News\Filament;

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
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\SpatieMediaLibraryImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Filamentboot\FilamentbootSite\Modules\News\Filament\NewsArticleResource\Pages\CreateNewsArticle;
use Filamentboot\FilamentbootSite\Modules\News\Filament\NewsArticleResource\Pages\EditNewsArticle;
use Filamentboot\FilamentbootSite\Modules\News\Filament\NewsArticleResource\Pages\ListNewsArticles;
use Filamentboot\FilamentbootSite\Modules\News\Models\NewsArticle;
use Filamentboot\Settings\UploadSettings;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use UnitEnum;

/**
 * 资讯文章后台资源
 *
 * 提供文章 CRUD，含内容 Tab（基本信息/内容/SEO/图片）、
 * 分类、标签、published_at 定时发布、置顶、封面图（UploadSettings 磁盘）。
 *
 * 发布态用 published_at 而非布尔：归档页按年月分组，且留空即草稿、
 * 填未来时间即定时发布，一个字段覆盖三种状态。
 */
class NewsArticleResource extends Resource
{
    /** @var class-string<NewsArticle> */
    protected static ?string $model = NewsArticle::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-newspaper';

    protected static string|UnitEnum|null $navigationGroup = '官网管理';

    protected static ?int $navigationSort = 35;

    protected static ?string $modelLabel = '资讯文章';

    protected static ?string $pluralModelLabel = '资讯文章';

    /**
     * 表单定义（基本信息/内容/SEO/图片四 Tab）
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
                        Toggle::make('is_featured')
                            ->label('置顶/精选'),
                        DateTimePicker::make('published_at')
                            ->label('发布时间（留空=草稿）')
                            ->seconds(false)
                            ->helperText('填未来时间即定时发布，到点后前台自动可见'),
                    ]),
                    Tab::make('内容')->schema([
                        TextInput::make('title_zh')
                            ->label('标题')
                            ->required()
                            ->maxLength(255),
                        Textarea::make('excerpt_zh')
                            ->label('摘要')
                            ->rows(3)
                            ->maxLength(500)
                            ->helperText('列表卡片与社交分享用，留空时前台从正文截取'),
                        RichEditorField::make('content_zh')
                            ->label('正文'),
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
                    ->conversion('thumb'),
                TextColumn::make('title_zh')
                    ->label('标题')
                    ->searchable()
                    ->limit(40),
                TextColumn::make('category.name_zh')
                    ->label('分类')
                    ->default('-'),
                IconColumn::make('publication_status')
                    ->label('状态')
                    ->getStateUsing(fn (NewsArticle $record): bool => $record->published_at !== null && $record->published_at <= now())
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
                SelectFilter::make('category_id')
                    ->label('分类')
                    ->relationship('category', 'name_zh')
                    ->preload(),
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
     * @return Builder<NewsArticle>
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
            'index'  => ListNewsArticles::route('/'),
            'create' => CreateNewsArticle::route('/create'),
            'edit'   => EditNewsArticle::route('/{record}/edit'),
        ];
    }

    /**
     * 解析默认上传磁盘（读 UploadSettings，降级 'public'）
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
