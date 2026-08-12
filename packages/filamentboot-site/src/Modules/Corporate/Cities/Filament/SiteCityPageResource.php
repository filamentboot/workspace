<?php

namespace Filamentboot\FilamentbootSite\Modules\Corporate\Cities\Filament;

use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreAction;
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
use Filamentboot\FilamentbootSite\Cms\Enums\PageStatus;
use Filamentboot\FilamentbootSite\Cms\Filament\RelationManagers\RevisionsRelationManager;
use Filamentboot\FilamentbootSite\Modules\Corporate\Cities\CityProfile;
use Filamentboot\FilamentbootSite\Modules\Corporate\Cities\Filament\SiteCityPageResource\Pages\CreateSiteCityPage;
use Filamentboot\FilamentbootSite\Modules\Corporate\Cities\Filament\SiteCityPageResource\Pages\EditSiteCityPage;
use Filamentboot\FilamentbootSite\Modules\Corporate\Cities\Filament\SiteCityPageResource\Pages\ListSiteCityPages;
use Filamentboot\FilamentbootSite\Modules\Corporate\Cities\Models\SiteCityPage;
use Filamentboot\FilamentbootSite\Modules\Corporate\Cities\Models\SiteRegion;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use UnitEnum;

/**
 * 城市页后台资源
 *
 * 与其它内容资源的两处不同：
 *
 *  1. **没有 slug 字段**——URL 段来自所选区划，页面无权改。
 *  2. **「城市概况」Tab 的字段是 config 生成的**，不是写死在这个类里的。
 *     字段表见 `config('filamentboot-site.city_pages.profile_fields')`。
 *     没配字段表时那个 Tab 整个不出现，而不是显示一个空壳。
 *
 * 区划本身没有后台资源：三千多条参考数据，能查能选就够了，
 * 做成一个可 CRUD 的列表只会让人以为它可以随手改——它由导入命令负责。
 *
 * @extends \Filament\Resources\Resource<SiteCityPage>
 */
class SiteCityPageResource extends Resource
{
    /** @var class-string<SiteCityPage> */
    protected static ?string $model = SiteCityPage::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-map-pin';

    protected static string|UnitEnum|null $navigationGroup = '官网管理';

    /**
     * 排在内容之后（案例 10 / 方案 20 / 套餐 25 / 产品 30 / 资讯 40）
     *
     * 城市页是投放，不是内容创作，日常打开频率远低于上面几个。
     */
    protected static ?int $navigationSort = 45;

    protected static ?string $modelLabel = '城市页';

    protected static ?string $pluralModelLabel = '城市页';

    /**
     * 表单定义
     */
    public static function form(Schema $schema): Schema
    {
        $profileFields = app(CityProfile::class)->fields();

        $tabs = [
            Tab::make('基本信息')->schema([
                Select::make('region_code')
                    ->label('所属区划')
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->searchable()
                    ->getSearchResultsUsing(static fn (string $search): array => static::regionOptions($search))
                    ->getOptionLabelUsing(static fn (?string $value): ?string => static::regionLabel($value))
                    ->helperText(
                        '决定页面地址：省级区划（直辖市）是 /city/{省}，地级是 /city/{省}/{市}。'
                        .'只能选省级与地级——县级不建页。一个区划只能有一个页面。'
                    ),
                TextInput::make('title_zh')
                    ->label('标题')
                    ->required()
                    ->maxLength(255)
                    ->placeholder('武汉全屋智能装修'),
                Textarea::make('description_zh')
                    ->label('简介')
                    ->rows(3)
                    ->helperText('列表卡片与 meta description 用。留空则回退到全局默认描述'),
                TextInput::make('sort')
                    ->label('排序权重')
                    ->numeric()
                    ->default(0)
                    ->helperText('同值时按区划代码排（即官方顺序）'),
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
                    ->helperText('分批放量就靠它：先建好全部记录，只给第一批填上时间')
                    ->required(fn (Get $get): bool => $get('status') === PageStatus::SCHEDULED->value),
            ]),
        ];

        if ($profileFields !== []) {
            $tabs[] = Tab::make('城市概况')
                ->schema(static::profileComponents($profileFields));
        }

        $tabs[] = Tab::make('正文（可选）')->schema([
            RichEditorField::make('content_zh')
                ->label('正文覆写')
                ->helperText(
                    '⚠️ **正常应该留空。** 页面主体由模板从上面的概况、下辖区县、'
                    .'同省城市自动渲染，不需要逐城写正文。这里填的内容会**追加**在概况之后，'
                    .'留给「这个城市确实有值得单独说的东西」那种个别情况。'
                ),
        ]);

        $tabs[] = Tab::make('SEO')->schema([
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
        ]);

        return $schema->components([
            Tabs::make('内容')->tabs($tabs)->columnSpanFull(),
        ]);
    }

    /**
     * 由字段表生成「城市概况」的表单控件
     *
     * 字段名走 `profile.{key}` 的点号形式，直接绑进 JSON 列（模型上 profile 转了 array）。
     *
     * @param  list<array{key: string, label: string, type: string, unit: string, help: string, options: list<string>}>  $fields
     * @return list<mixed>
     */
    protected static function profileComponents(array $fields): array
    {
        $components = [];

        foreach ($fields as $field) {
            $name = 'profile.'.$field['key'];
            $help = $field['help'] !== '' ? $field['help'] : null;

            $components[] = match ($field['type']) {
                'textarea' => Textarea::make($name)
                    ->label($field['label'])
                    ->rows(3)
                    ->helperText($help),
                'select' => Select::make($name)
                    ->label($field['label'])
                    ->options(array_combine($field['options'], $field['options']))
                    ->native(false)
                    ->helperText($help),
                'number' => TextInput::make($name)
                    ->label($field['label'])
                    ->numeric()
                    ->suffix($field['unit'] !== '' ? $field['unit'] : null)
                    ->helperText($help),
                default => TextInput::make($name)
                    ->label($field['label'])
                    ->maxLength(255)
                    ->suffix($field['unit'] !== '' ? $field['unit'] : null)
                    ->helperText($help),
            };
        }

        return $components;
    }

    /**
     * 区划下拉的搜索结果（只出省级与地级）
     *
     * 标签带上省名——江苏泰州与浙江台州的拼音一样，光看市名选不对。
     *
     * @return array<string, string>
     */
    protected static function regionOptions(string $search): array
    {
        $search = trim($search);

        if ($search === '') {
            return [];
        }

        $regions = SiteRegion::query()
            ->whereIn('level', [SiteRegion::LEVEL_PROVINCE, SiteRegion::LEVEL_CITY])
            ->whereNotNull('slug')
            ->where(fn (Builder $query): Builder => $query
                ->where('name', 'like', '%'.$search.'%')
                ->orWhere('slug', 'like', $search.'%'))
            ->with('parent')
            ->ordered()
            ->limit(50)
            ->get();

        $options = [];

        foreach ($regions as $region) {
            $options[$region->code] = static::describeRegion($region);
        }

        return $options;
    }

    /**
     * 已选区划的显示文本
     */
    protected static function regionLabel(?string $code): ?string
    {
        if ($code === null || $code === '') {
            return null;
        }

        $region = SiteRegion::query()->with('parent')->where('code', $code)->first();

        return $region !== null ? static::describeRegion($region) : null;
    }

    /**
     * 「江苏省 / 泰州市」这样的区划描述
     */
    protected static function describeRegion(SiteRegion $region): string
    {
        $parent = $region->parent;

        return ($parent !== null ? $parent->name.' / ' : '').$region->name;
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
                TextColumn::make('region.name')
                    ->label('区划')
                    ->searchable()
                    ->description(fn (SiteCityPage $record): string => $record->region?->parent?->name ?? '直辖'),
                TextColumn::make('region_code')
                    ->label('区划代码')
                    ->toggleable(isToggledHiddenByDefault: true),
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
                    ->sortable()
                    ->placeholder('草稿'),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('状态')
                    ->options(PageStatus::options()),
                SelectFilter::make('level')
                    ->label('层级')
                    ->options([
                        (string) SiteRegion::LEVEL_PROVINCE => '省级（直辖市）',
                        (string) SiteRegion::LEVEL_CITY     => '地级',
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query->when(
                        ($data['value'] ?? '') !== '',
                        fn (Builder $inner): Builder => $inner->whereIn(
                            'region_code',
                            SiteRegion::query()->where('level', (int) $data['value'])->select('code')
                        )
                    )),
                TrashedFilter::make(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
                RestoreAction::make(),
            ])
            // 按区划代码排 = 官方顺序（京津冀 → 晋蒙 → 东北 → 华东…），
            // 同省的城市一定挨着，核对采集结果时不用来回翻
            ->defaultSort('region_code');
    }

    /**
     * 覆盖 Eloquent 查询，去除软删除作用域并预加载区划
     *
     * 列表每行都要显示区划名与省名，不预加载就是每行两次查询。
     *
     * @return Builder<SiteCityPage>
     */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([SoftDeletingScope::class])
            ->with(['region', 'region.parent']);
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
            'index'  => ListSiteCityPages::route('/'),
            'create' => CreateSiteCityPage::route('/create'),
            'edit'   => EditSiteCityPage::route('/{record}/edit'),
        ];
    }
}
