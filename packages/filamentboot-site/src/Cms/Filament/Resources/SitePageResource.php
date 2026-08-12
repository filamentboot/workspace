<?php

namespace Filamentboot\FilamentbootSite\Cms\Filament\Resources;

use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreAction;
use Filament\Forms\Components\Builder as BuilderField;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\RichEditor as RichEditorField;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Filamentboot\FilamentbootSite\Cms\Blocks\BlockRegistry;
use Filamentboot\FilamentbootSite\Cms\Enums\PageStatus;
use Filamentboot\FilamentbootSite\Cms\Filament\RelationManagers\RevisionsRelationManager;
use Filamentboot\FilamentbootSite\Cms\Filament\Resources\SitePageResource\Pages\CreateSitePage;
use Filamentboot\FilamentbootSite\Cms\Filament\Resources\SitePageResource\Pages\EditSitePage;
use Filamentboot\FilamentbootSite\Cms\Filament\Resources\SitePageResource\Pages\ListSitePages;
use Filamentboot\FilamentbootSite\Cms\Models\SitePage;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use UnitEnum;

/**
 * CMS 页面后台资源
 *
 * 提供页面 CRUD，含内容 Tab（内容/SEO）、slug、Phase 9 富文本编辑器。
 * 页面无封面图（D-10-16 简化），社交分享图走 seo_og_image 字段。
 *
 * 发布控制已从 is_published 布尔开关换成 PageStatus 状态机（#11）：
 * 前台可见性由 SitePage::scopePublished() 判定 status + published_at，
 * 表单必须跟着改，否则「点了发布前台看不到」。
 *
 * 状态流转 Action（编辑者只能提交审核、发布者才能发布）在 EditSitePage，
 * 按状态分 Tab 在 ListSitePages，转移规则在 PageStatus 枚举上（#14）。
 *
 * 页面区块用 Filament Builder 承载（#14）：它存的正是 [{type, data}, ...]，
 * 与 Cms\Blocks 的区块契约天然一致，不需要转换层。保存侧经 BlockSanitizer
 * 过一遍富文本白名单（#13）。
 *
 * @extends \Filament\Resources\Resource<SitePage>
 */
class SitePageResource extends Resource
{
    /** @var class-string<SitePage> */
    protected static ?string $model = SitePage::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-document';

    protected static string|UnitEnum|null $navigationGroup = '官网管理';

    protected static ?int $navigationSort = 40;

    protected static ?string $modelLabel = '静态页面';

    protected static ?string $pluralModelLabel = '静态页面';

    /**
     * 表单定义（内容/SEO 两 Tab，slug + 状态 + 版式置顶内容 Tab）
     */
    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Tabs::make('内容')
                ->tabs([
                    Tab::make('内容')->schema([
                        TextInput::make('slug')
                            ->label('URL Slug')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->rules(['alpha_dash'])
                            ->maxLength(255),
                        Select::make('status')
                            ->label('发布状态')
                            ->options(PageStatus::options())
                            ->default(PageStatus::DRAFT->value)
                            ->required()
                            ->native(false)
                            ->live()
                            ->helperText('选「定时发布」并填写下方发布时间，到点后前台自动可见，无需队列或定时任务'),
                        DateTimePicker::make('published_at')
                            ->label('发布时间')
                            ->seconds(false)
                            ->helperText('留空表示立即生效。「定时发布」状态下必须填写未来时间。')
                            ->required(fn (Get $get): bool => $get('status') === PageStatus::SCHEDULED->value),
                        Select::make('template')
                            ->label('页面版式')
                            ->options(fn (): array => (array) config('filamentboot-site.page_templates', ['default' => '标准页面']))
                            ->default('default')
                            ->required()
                            ->native(false)
                            ->helperText('当前主题没有对应版式视图时自动回退标准页面，不会 404'),
                        TextInput::make('sort')
                            ->label('排序权重')
                            ->numeric()
                            ->default(0)
                            ->helperText('只影响后台列表的默认顺序（数字越小越靠前）。页面没有前台列表，前台不受它影响'),
                        TextInput::make('title_zh')
                            ->label('标题')
                            ->required()
                            ->maxLength(255),
                        RichEditorField::make('content_zh')
                            ->label('正文'),
                        BuilderField::make('blocks')
                            ->label('页面区块')
                            ->helperText('区块渲染在正文之后，两者可以并存')
                            ->blocks(static::blockComponents())
                            ->collapsible()
                            ->cloneable()
                            ->columnSpanFull(),
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
                        TextInput::make('seo_og_image')
                            ->label('社交分享图 URL')
                            ->url()
                            ->maxLength(1024)
                            ->helperText('留空时回退到站点设置里的默认 Open Graph 图'),
                    ]),
                ])
                ->columnSpanFull(),
        ]);
    }

    /**
     * 把注册表里的区块装配成 Builder 的 Block 组件（#14）
     *
     * Filament Builder 存的正是 [{type, data}, ...]，与区块契约天然一致，
     * 不需要任何转换层——SitePage.blocks 已 cast 为 array，前台
     * Cms\Rendering\BlockRenderer 直接读同一份结构。
     *
     * 从注册表动态取而非写死清单：宿主在自己的 ServiceProvider 里
     * app(BlockRegistry::class)->register(new MyBlock) 之后，后台表单自动出现该区块。
     *
     * @return list<BuilderField\Block>
     */
    protected static function blockComponents(): array
    {
        $blocks = [];

        foreach (app(BlockRegistry::class)->all() as $block) {
            $blocks[] = BuilderField\Block::make($block->key())
                ->label($block->label())
                ->schema($block->schema());
        }

        return $blocks;
    }

    /**
     * 列表表格定义
     */
    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title_zh')
                    ->label('标题')
                    ->searchable()
                    ->limit(30),
                TextColumn::make('slug')
                    ->label('Slug')
                    ->searchable(),
                TextColumn::make('status')
                    ->label('状态')
                    ->badge()
                    ->formatStateUsing(fn (mixed $state): string => $state instanceof PageStatus
                        ? $state->label()
                        : (string) (PageStatus::tryFrom((string) $state)?->label() ?? $state))
                    ->color(fn (mixed $state): string => $state instanceof PageStatus
                        ? $state->color()
                        : (PageStatus::tryFrom((string) $state)?->color() ?? 'gray')),
                TextColumn::make('published_at')
                    ->label('发布时间')
                    ->dateTime('Y-m-d H:i')
                    ->placeholder('-')
                    ->sortable(),
                TextColumn::make('updated_at')
                    ->label('更新时间')
                    ->dateTime('Y-m-d H:i')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('状态')
                    ->options(PageStatus::options()),
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
     * @return Builder<SitePage>
     */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([SoftDeletingScope::class]);
    }

    /**
     * 关系管理器（#15 版本历史）
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
            'index'  => ListSitePages::route('/'),
            'create' => CreateSitePage::route('/create'),
            'edit'   => EditSitePage::route('/{record}/edit'),
        ];
    }
}
