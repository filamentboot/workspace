<?php

namespace Filamentboot\FilamentbootSite\Cms\Filament\Resources;

use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filamentboot\FilamentbootSite\Cms\Filament\Resources\SiteMenuItemResource\Pages\SiteMenuItemTree;
use Filamentboot\FilamentbootSite\Cms\Models\SiteMenuItem;
use Filamentboot\FilamentbootSite\Cms\Models\SitePage;

/**
 * 前台导航菜单项资源（#17）
 *
 * 只有一个树形页，且**不进导航**：入口是 SiteMenuResource 列表页的
 * 「管理菜单项」动作（树必须知道自己在编哪条菜单，从导航直接点进来没有这个上下文）。
 *
 * 树形操作照主包 Menus\MenuResource + Pages\MenuTree 抄。SiteMenuItem 已覆盖好
 * filament-tree 的三处列名约定（sort / label / parent_id=0）。
 *
 * ⚠️ target 列一列四用（页面 id / 路由名 / 外链 / 锚点），但表单里拆成四个
 * 独立字段 target_page / target_route / target_url / target_anchor，
 * 存取时由 collapseTarget() 与 expandTarget() 互转。
 * 不给四个控件都取名 target：同一 schema 里出现重名组件时状态绑定行为
 * 依赖 Filament 内部实现，升级即可能静默失效——那会表现为「填了链接存进去是空的」。
 */
class SiteMenuItemResource extends Resource
{
    /** @var class-string<SiteMenuItem> */
    protected static ?string $model = SiteMenuItem::class;

    protected static ?string $recordTitleAttribute = 'label';

    protected static ?string $modelLabel = '菜单项';

    protected static ?string $pluralModelLabel = '菜单项';

    /**
     * type → 表单里承载 target 的字段名
     *
     * @var array<string, string>
     */
    public const TARGET_FIELDS = [
        'page'   => 'target_page',
        'route'  => 'target_route',
        'url'    => 'target_url',
        'anchor' => 'target_anchor',
    ];

    /**
     * 不进后台导航
     *
     * 树页必须带 ?menu= 才知道编的是哪条菜单，导航里没法给出这个参数。
     */
    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    /**
     * 把表单的四个 target_* 字段收敛回单个 target 列
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function collapseTarget(array $data): array
    {
        $field = self::TARGET_FIELDS[$data['type'] ?? ''] ?? null;

        $data['target'] = $field !== null ? ($data[$field] ?? null) : null;

        // 未选中的那几个字段不能进 $fillable 之外的写入，直接剔掉
        foreach (self::TARGET_FIELDS as $candidate) {
            unset($data[$candidate]);
        }

        return $data;
    }

    /**
     * 把库里的 target 展开到对应的表单字段（编辑时回填）
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function expandTarget(array $data): array
    {
        $field = self::TARGET_FIELDS[$data['type'] ?? ''] ?? null;

        if ($field !== null) {
            $data[$field] = $data['target'] ?? null;
        }

        return $data;
    }

    /**
     * 菜单项表单
     *
     * 四种 type 各自需要不同的输入控件，用 live() + visible() 切换：
     * 让作者在一个自由文本框里既填页面 id 又填路由名，等于把校验推给运气。
     */
    public static function form(Schema $schema): Schema
    {
        return $schema->components(static::formComponents());
    }

    /**
     * 表单组件列表（未挂载到任何 Schema 容器）
     *
     * 与 form() 分开的唯一理由是给树页的模态动作用，见
     * SiteMenuItemTree::getFormSchema() 上的说明——那里必须拿到一批**没被绑过容器**
     * 的新组件，走 form() 会先绑到一个临时容器上，状态路径被缓存成裸字段名后就再也改不回来。
     *
     * @return list<Component>
     */
    public static function formComponents(): array
    {
        return [
            // menu_id 由树页在创建时注入（表单是静态的，拿不到当前菜单）
            Hidden::make('menu_id'),
            Hidden::make('parent_id')
                ->default(SiteMenuItem::defaultParentKey()),
            TextInput::make('label')
                ->label('显示文字')
                ->required()
                ->maxLength(50),
            Select::make('type')
                ->label('链接类型')
                ->options([
                    'page'   => '站内页面',
                    'route'  => '固定栏目',
                    'url'    => '外部链接',
                    'anchor' => '页内锚点',
                ])
                ->default('page')
                ->required()
                ->native(false)
                ->live(),
            Select::make('target_page')
                ->label('目标页面')
                ->options(fn (): array => SitePage::query()
                    ->orderBy('title_zh')
                    ->pluck('title_zh', 'id')
                    ->all())
                ->searchable()
                ->required(fn (Get $get): bool => $get('type') === 'page')
                ->visible(fn (Get $get): bool => $get('type') === 'page')
                // 存 id 不存 slug：slug 改了菜单不能断，站内链接应当直接跟着走
                ->helperText('存的是页面 id，改 slug 不会让菜单失效。未发布的页面不会出现在前台导航里。'),
            Select::make('target_route')
                ->label('目标栏目')
                ->options(fn (): array => (array) config('filamentboot-site.menu.allowed_routes', []))
                ->required(fn (Get $get): bool => $get('type') === 'route')
                ->visible(fn (Get $get): bool => $get('type') === 'route')
                ->native(false),
            TextInput::make('target_url')
                ->label('外部链接')
                ->required(fn (Get $get): bool => $get('type') === 'url')
                ->visible(fn (Get $get): bool => $get('type') === 'url')
                ->maxLength(500)
                ->helperText('必须是 http(s):// 开头的完整地址，或 tel: / mailto:。其它协议前台不会渲染。'),
            TextInput::make('target_anchor')
                ->label('锚点')
                ->required(fn (Get $get): bool => $get('type') === 'anchor')
                ->visible(fn (Get $get): bool => $get('type') === 'anchor')
                ->maxLength(100)
                ->rules(['regex:/^#[A-Za-z0-9_\-]+$/'])
                ->helperText('以 # 开头，如 #contact'),
            TextInput::make('sort')
                ->label('排序')
                ->numeric()
                ->default(0)
                ->required()
                ->helperText('也可以直接在树上拖拽调整'),
            Toggle::make('open_in_new')
                ->label('新窗口打开')
                ->default(false),
        ];
    }

    /**
     * 路由页面映射（只有树页）
     *
     * @return array<string, mixed>
     */
    public static function getPages(): array
    {
        return [
            'index' => SiteMenuItemTree::route('/'),
        ];
    }
}
