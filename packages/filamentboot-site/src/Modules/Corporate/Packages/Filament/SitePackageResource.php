<?php

namespace Filamentboot\FilamentbootSite\Modules\Corporate\Packages\Filament;

use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Repeater;
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
use Filamentboot\FilamentbootSite\Modules\Corporate\Packages\Enums\HouseLayout;
use Filamentboot\FilamentbootSite\Modules\Corporate\Packages\Enums\PackageTier;
use Filamentboot\FilamentbootSite\Modules\Corporate\Packages\Filament\SitePackageResource\Pages\CreateSitePackage;
use Filamentboot\FilamentbootSite\Modules\Corporate\Packages\Filament\SitePackageResource\Pages\EditSitePackage;
use Filamentboot\FilamentbootSite\Modules\Corporate\Packages\Filament\SitePackageResource\Pages\ListSitePackages;
use Filamentboot\FilamentbootSite\Modules\Corporate\Packages\Models\SitePackage;
use Filamentboot\FilamentbootSite\SiteServiceProvider;
use Filamentboot\Settings\UploadSettings;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use UnitEnum;

/**
 * 全屋智能套餐后台资源
 *
 * 结构照 `SiteSolutionResource` 抄（内容 Tab / SEO / 图片 / 标签 / 发布置顶），
 * 差别在两处：
 *
 *  1. **基本信息里多了户型 × 档位 × 面积段 × 价格**——套餐的存在意义就是这几个维度，
 *     列表页也按它们筛。
 *  2. **多了一个「包含清单」Tab**，用 `Repeater` 编辑 `items` JSON。这是包里第一个
 *     重复结构字段（记进七期账，见模型类注释）。
 *
 * @extends \Filament\Resources\Resource<SitePackage>
 */
class SitePackageResource extends Resource
{
    /** @var class-string<SitePackage> */
    protected static ?string $model = SitePackage::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-squares-2x2';

    protected static string|UnitEnum|null $navigationGroup = '官网管理';

    /**
     * 排在智能方案（20）之后、智能产品之前
     *
     * 导航顺序对着访客的决策路径：先看方案（这类需求怎么解决）→ 再看套餐
     * （我家这个户型做下来什么配置多少钱）→ 最后才看单品。
     */
    protected static ?int $navigationSort = 25;

    /**
     * 「全屋套餐」是 decoration 专属叫法，`SitePackage` 本身已判定「包多候选」
     * （六期双向边界梳理），software 模板不建这类内容，但 Resource 仍在后台侧边栏
     * 里对所有主题可见——标签至少不该在 software 站上显示装修用语
     */
    public static function getModelLabel(): string
    {
        return SiteServiceProvider::resolveActiveTheme() === 'software' ? '套餐' : '全屋套餐';
    }

    public static function getPluralModelLabel(): string
    {
        return static::getModelLabel();
    }

    /**
     * 表单定义
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
                        Select::make('house_layout')
                            ->label('户型')
                            ->options(HouseLayout::options())
                            ->native(false)
                            ->helperText('列表页按它筛。留空的套餐不出现在户型筛选里，只能从「全部」进'),
                        Select::make('tier')
                            ->label('档位')
                            ->options(PackageTier::options())
                            ->native(false)
                            ->helperText('同一户型下按「定制 → 舒适 → 豪华」排，顺序由枚举定，不用手工调排序值'),
                        TextInput::make('area_range')
                            ->label('适用面积段')
                            ->maxLength(50)
                            ->placeholder('60-90㎡'),
                        TextInput::make('price')
                            ->label('参考价')
                            ->numeric()
                            ->minValue(0)
                            ->prefix('¥')
                            ->helperText('留空则前台显示「咨询价格」。⚠️ 只填有出处的真实售价，拿不到就留空'),
                        TextInput::make('price_note')
                            ->label('价格口径')
                            ->maxLength(255)
                            ->default('参考价，最终以实际量房与选型为准'),
                        TextInput::make('duration')
                            ->label('工期')
                            ->maxLength(255)
                            ->placeholder('3-5 天'),
                        TextInput::make('warranty')
                            ->label('质保')
                            ->maxLength(255)
                            ->placeholder('整机 1 年，施工 2 年'),
                        Select::make('tags')
                            ->label('标签')
                            ->relationship('tags', 'name_zh')
                            ->multiple()
                            ->preload(),
                        Toggle::make('is_featured')
                            ->label('置顶')
                            ->helperText('列表页排在最前面，不受下面「户型小→大、档位低→高」那套顺序约束'),
                        TextInput::make('sort')
                            ->label('排序权重')
                            ->numeric()
                            ->default(0)
                            ->helperText('套餐列表按「户型小→大、档位低→高」排，这个值只在两者都相同时才起作用'),
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
                    Tab::make('包含清单')->schema([
                        Repeater::make('items')
                            ->label('包含项')
                            ->helperText(
                                '装修者最想看的就是这张表：装什么、几个、干什么用、放在哪儿。'
                                .'名称留空的行前台不显示。⚠️ 清单里的文字**站内搜索搜不到**（JSON 列的已知限制），'
                                .'关键信息记得同时写进简介或正文。'
                            )
                            ->addActionLabel('加一项')
                            ->collapsed()
                            ->itemLabel(fn (array $state): ?string => $state['name'] ?? null)
                            ->reorderable()
                            ->default([])
                            ->schema([
                                TextInput::make('name')
                                    ->label('名称')
                                    ->required()
                                    ->maxLength(100),
                                TextInput::make('quantity')
                                    ->label('数量')
                                    ->maxLength(30)
                                    ->placeholder('1 或 6'),
                                TextInput::make('purpose')
                                    ->label('用途')
                                    ->maxLength(200),
                                TextInput::make('location')
                                    ->label('摆放位置')
                                    ->maxLength(200),
                            ])
                            ->columns(2),
                        Textarea::make('excludes')
                            ->label('不含项')
                            ->rows(3)
                            ->helperText('报价单上最容易起争议的部分，写清楚比不写好'),
                    ]),
                    Tab::make('内容')->schema([
                        TextInput::make('title_zh')
                            ->label('标题')
                            ->required()
                            ->maxLength(255),
                        Textarea::make('description_zh')
                            ->label('简介')
                            ->rows(3)
                            ->helperText('列表卡片与社交分享用'),
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
                            ->imageEditorAspectRatios(['1:1'])
                            ->helperText('建议不小于 1200×1200，**比例 1:1**。套餐封面通常是「户型剖面 + 环绕设备图标」的方图，标题压在画面顶部，所以前台的卡片与详情页都用方形容器、不做 16:9 通栏大图——那会把标题连同上半张图一起切掉。裁剪框内就是最终构图，系统只做等比缩放、不再自动裁切。'),
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
                TextColumn::make('house_layout')
                    ->label('户型')
                    ->formatStateUsing(fn (?HouseLayout $state): string => $state?->label() ?? '—'),
                TextColumn::make('tier')
                    ->label('档位')
                    ->formatStateUsing(fn (?PackageTier $state): string => $state?->label() ?? '—')
                    ->badge(),
                TextColumn::make('price')
                    ->label('参考价')
                    ->money('CNY')
                    ->placeholder('咨询价格'),
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
                SelectFilter::make('status')
                    ->label('状态')
                    ->options(PageStatus::options()),
                SelectFilter::make('house_layout')
                    ->label('户型')
                    ->options(HouseLayout::options()),
                SelectFilter::make('tier')
                    ->label('档位')
                    ->options(PackageTier::options()),
                TrashedFilter::make(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
                RestoreAction::make(),
            ])
            // 后台默认按对比顺序排（户型小→大、档位低→高），与前台列表一致：
            // 后台看到的顺序和访客看到的一样，核对内容时不用来回换算
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->orderedForCompare());
    }

    /**
     * 覆盖 Eloquent 查询，去除软删除作用域
     *
     * @return Builder<SitePackage>
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
            'index'  => ListSitePackages::route('/'),
            'create' => CreateSitePackage::route('/create'),
            'edit'   => EditSitePackage::route('/{record}/edit'),
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
