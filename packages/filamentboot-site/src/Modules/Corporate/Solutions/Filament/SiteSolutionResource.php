<?php

namespace Filamentboot\FilamentbootSite\Modules\Corporate\Solutions\Filament;

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
use Filamentboot\FilamentbootSite\Modules\Corporate\Solutions\Filament\SiteSolutionResource\Pages\CreateSiteSolution;
use Filamentboot\FilamentbootSite\Modules\Corporate\Solutions\Filament\SiteSolutionResource\Pages\EditSiteSolution;
use Filamentboot\FilamentbootSite\Modules\Corporate\Solutions\Filament\SiteSolutionResource\Pages\ListSiteSolutions;
use Filamentboot\FilamentbootSite\Modules\Corporate\Solutions\Models\SiteSolution;
use Filamentboot\FilamentbootSite\SiteServiceProvider;
use Filamentboot\Settings\UploadSettings;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use UnitEnum;

/**
 * 智能方案后台资源
 *
 * 提供方案 CRUD，含内容 Tab（基本信息/内容/SEO/图片）、
 * 标签、发布状态、置顶、Phase 9 富文本编辑器与封面图。
 *
 * @extends \Filament\Resources\Resource<SiteSolution>
 */
class SiteSolutionResource extends Resource
{
    /** @var class-string<SiteSolution> */
    protected static ?string $model = SiteSolution::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-cube';

    protected static string|UnitEnum|null $navigationGroup = '官网管理';

    protected static ?int $navigationSort = 20;

    /**
     * 「智能方案」是 decoration 专属叫法，software 模板对应的是「应用场景」
     * （导航标签同名，六期双向边界梳理，批次 9 顺手扩到 Resource 标签）
     */
    public static function getModelLabel(): string
    {
        return SiteServiceProvider::resolveActiveTheme() === 'software' ? '应用场景' : '智能方案';
    }

    public static function getPluralModelLabel(): string
    {
        return static::getModelLabel();
    }

    /**
     * 表单定义（内容 Tab + SEO + 图片 + 标签 + 发布置顶）
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
                        Select::make('tags')
                            ->label('标签')
                            ->relationship('tags', 'name_zh')
                            ->multiple()
                            ->preload(),
                        TextInput::make('price_range')
                            ->label('价格范围')
                            ->maxLength(100),
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
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
                RestoreAction::make(),
            ])
            ->defaultSort('published_at', 'desc');
    }

    /**
     * 覆盖 Eloquent 查询，去除软删除作用域
     *
     * @return Builder<SiteSolution>
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
            'index'  => ListSiteSolutions::route('/'),
            'create' => CreateSiteSolution::route('/create'),
            'edit'   => EditSiteSolution::route('/{record}/edit'),
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
