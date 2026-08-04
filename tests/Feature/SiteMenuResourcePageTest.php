<?php

use Filament\Facades\Filament;
use Filamentboot\FilamentbootSite\Cms\Filament\Resources\SiteMenuItemResource;
use Filamentboot\FilamentbootSite\Cms\Filament\Resources\SiteMenuItemResource\Pages\SiteMenuItemTree;
use Filamentboot\FilamentbootSite\Cms\Filament\Resources\SiteMenuResource;
use Filamentboot\FilamentbootSite\Cms\Filament\Resources\SiteMenuResource\Pages\ListSiteMenus;
use Filamentboot\FilamentbootSite\Cms\Models\SiteMenu;
use Filamentboot\FilamentbootSite\Cms\Models\SiteMenuItem;
use Filamentboot\FilamentbootSite\SitePlugin;
use Filamentboot\Models\AdminUser;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * 前台导航后台页面测试（#17）
 *
 * 覆盖场景：
 * - 菜单列表渲染，manage_site_menu 缺失时整个资源不可见
 * - 菜单项树页按 ?menu= 过滤，不会把两条菜单的项混在一起
 * - 四个 target_* 字段与 target 列的互转（collapseTarget / expandTarget）
 *
 * 后台路由注册手法同 SiteContactResourcePageTest。
 *
 * @group site
 */
beforeEach(function () {
    $panel = Filament::getPanel('admin');
    $panel->plugin(SitePlugin::make());

    require base_path('vendor/filament/filament/routes/web.php');

    app('router')->getRoutes()->refreshNameLookups();
    app('router')->getRoutes()->refreshActionLookups();

    Filament::setCurrentPanel($panel);

    app(PermissionRegistrar::class)->forgetCachedPermissions();

    Permission::firstOrCreate(['name' => 'manage_site_menu', 'guard_name' => 'admin']);
});

/**
 * 创建并登录一个管理员
 *
 * @param  list<string>  $permissions
 */
function loginMenuManager(array $permissions): AdminUser
{
    $role = Role::create(['name' => 'menu-role-'.uniqid(), 'guard_name' => 'admin']);

    foreach ($permissions as $permission) {
        $role->givePermissionTo($permission);
    }

    $user = AdminUser::factory()->create();
    $user->assignRole($role);

    test()->actingAs($user, 'admin');

    return $user;
}

/**
 * 菜单列表渲染并显示菜单项计数
 */
it('菜单列表渲染', function () {
    loginMenuManager(['manage_site_menu']);

    $menu = SiteMenu::create(['key' => 'main', 'name' => '顶部导航']);
    SiteMenuItem::create([
        'menu_id'   => $menu->getKey(),
        'parent_id' => SiteMenuItem::defaultParentKey(),
        'type'      => 'anchor',
        'label'     => '锚点项',
        'target'    => '#a',
        'sort'      => 0,
    ]);

    Livewire::test(ListSiteMenus::class)
        ->assertOk()
        ->assertSee('顶部导航')
        ->assertSee('main');
});

/**
 * 无 manage_site_menu 权限时整个资源不可访问
 */
it('无权限时菜单资源不可见', function () {
    loginMenuManager([]);

    expect(SiteMenuResource::canViewAny())->toBeFalse()
        ->and(SiteMenuItemResource::canViewAny())->toBeFalse();
});

/**
 * 菜单项树页按 ?menu= 过滤，两条菜单互不串台
 */
it('菜单项树页按菜单过滤', function () {
    loginMenuManager(['manage_site_menu']);

    $main   = SiteMenu::create(['key' => 'main', 'name' => '顶部导航']);
    $footer = SiteMenu::create(['key' => 'footer', 'name' => '页脚导航']);

    foreach ([[$main, '顶部的项'], [$footer, '页脚的项']] as [$menu, $label]) {
        SiteMenuItem::create([
            'menu_id'   => $menu->getKey(),
            'parent_id' => SiteMenuItem::defaultParentKey(),
            'type'      => 'anchor',
            'label'     => $label,
            'target'    => '#x',
            'sort'      => 0,
        ]);
    }

    Livewire::test(SiteMenuItemTree::class, ['menu' => 'main'])
        ->assertOk()
        ->assertSee('顶部的项')
        ->assertDontSee('页脚的项');

    Livewire::test(SiteMenuItemTree::class, ['menu' => 'footer'])
        ->assertOk()
        ->assertSee('页脚的项')
        ->assertDontSee('顶部的项');
});

/**
 * 未指定菜单时回落到第一条，而不是报错页
 *
 * 「刚装上包、菜单表为空」不该变成一个 404。
 */
it('未指定菜单时回落到第一条', function () {
    loginMenuManager(['manage_site_menu']);

    SiteMenu::create(['key' => 'main', 'name' => '顶部导航']);
    SiteMenu::create(['key' => 'footer', 'name' => '页脚导航']);

    Livewire::test(SiteMenuItemTree::class)
        ->assertOk()
        ->assertSee('顶部导航');
});

/**
 * 菜单表为空时树页仍能打开（空树）
 */
it('无任何菜单时树页仍可打开', function () {
    loginMenuManager(['manage_site_menu']);

    Livewire::test(SiteMenuItemTree::class)->assertOk();
});

/**
 * 树页的新建动作真正把菜单项写进库（#23）
 *
 * 这条是 #23 的验收硬要求。此前本文件只覆盖 collapseTarget / expandTarget 两个纯函数
 * 与树的读取过滤，一条都没真正建出过菜单项——而「建菜单项」正好是坏的。
 */
it('树页新建动作真正建出菜单项', function () {
    loginMenuManager(['manage_site_menu']);

    $menu = SiteMenu::create(['key' => 'main', 'name' => '顶部导航']);

    Livewire::test(SiteMenuItemTree::class, ['menu' => 'main'])
        ->callAction('create', [
            'label'         => '联系我们',
            'type'          => 'anchor',
            'target_anchor' => '#contact',
            'sort'          => 3,
        ])
        ->assertHasNoActionErrors();

    $item = SiteMenuItem::sole();

    expect($item->menu_id)->toBe($menu->getKey())
        ->and($item->parent_id)->toBe(SiteMenuItem::defaultParentKey())
        ->and($item->type)->toBe('anchor')
        ->and($item->label)->toBe('联系我们')
        ->and($item->target)->toBe('#contact');
});

/**
 * 新建时 menu_id 跟随 ?menu=，不会串到别的菜单下
 */
it('新建的菜单项归属当前 ?menu= 那条', function () {
    loginMenuManager(['manage_site_menu']);

    SiteMenu::create(['key' => 'main', 'name' => '顶部导航']);
    $footer = SiteMenu::create(['key' => 'footer', 'name' => '页脚导航']);

    Livewire::test(SiteMenuItemTree::class, ['menu' => 'footer'])
        ->callAction('create', [
            'label'        => '关于我们',
            'type'         => 'route',
            'target_route' => 'site.cases.index',
            'sort'         => 0,
        ])
        ->assertHasNoActionErrors();

    expect(SiteMenuItem::sole()->menu_id)->toBe($footer->getKey());
});

/**
 * 树页的编辑动作回填 target 并写回
 */
it('树页编辑动作回填并写回 target', function () {
    loginMenuManager(['manage_site_menu']);

    $menu = SiteMenu::create(['key' => 'main', 'name' => '顶部导航']);
    $item = SiteMenuItem::create([
        'menu_id'   => $menu->getKey(),
        'parent_id' => SiteMenuItem::defaultParentKey(),
        'type'      => 'anchor',
        'label'     => '原文字',
        'target'    => '#old',
        'sort'      => 0,
    ]);

    Livewire::test(SiteMenuItemTree::class, ['menu' => 'main'])
        ->call('mountTreeAction', 'edit', (string) $item->getKey())
        // 回填走 mutateRecordDataUsing → expandTarget，target 应已展开到 target_anchor
        ->assertSet('mountedActions.0.data.target_anchor', '#old')
        ->set('mountedActions.0.data.label', '改过的文字')
        ->set('mountedActions.0.data.target_anchor', '#new')
        ->call('callMountedAction')
        ->assertHasNoActionErrors();

    $item->refresh();

    expect($item->label)->toBe('改过的文字')
        ->and($item->target)->toBe('#new');
});

/**
 * 模态表单的状态路径必须挂在 mountedActions 下（#23 回归护栏）
 *
 * 基类 TreePage::getFormSchema() 先把组件绑到一个 statePath 为空的临时 Schema 上，
 * Filament 5 在那次解析里就把字段的绝对状态路径缓存成裸字段名。动作随后用
 * mountedActions.0.data 重新收容这批组件时缓存不会重算，前端 @entangle 去找页面上
 * 并不存在的 Livewire 属性 `type`，浏览器报 Entangle Error，弹窗根本显示不出来。
 *
 * 不断言渲染的 HTML：Filament 的模态体是客户端惰性渲染的，Livewire::test 拿到的
 * action-modals 分区是空的，断言 HTML 会恒假。这里直接问服务端解析出的状态路径。
 */
it('模态表单字段绑到 mountedActions 状态路径', function () {
    loginMenuManager(['manage_site_menu']);

    SiteMenu::create(['key' => 'main', 'name' => '顶部导航']);

    $schema = Livewire::test(SiteMenuItemTree::class, ['menu' => 'main'])
        ->call('mountAction', 'create')
        ->instance()
        ->getSchema('mountedActionSchema0');

    expect($schema)->not->toBeNull()
        ->and($schema->getStatePath())->toBe('mountedActions.0.data');

    $paths = [];

    foreach ($schema->getComponents() as $component) {
        if (method_exists($component, 'getName') && method_exists($component, 'getStatePath')) {
            $paths[$component->getName()] = $component->getStatePath();
        }
    }

    // 只断言默认可见的字段：四个 target_* 由 visible() 按 type 切换，
    // type 默认是 page，此刻 target_anchor 不在组件列表里。
    // type 本身正是当初报 Entangle Error 的那个字段，够用。
    expect($paths['label'] ?? null)->toBe('mountedActions.0.data.label')
        ->and($paths['type'] ?? null)->toBe('mountedActions.0.data.type');
});

/**
 * collapseTarget：四个表单字段收敛成单个 target 列
 */
it('表单字段收敛成 target 列', function () {
    $cases = [
        ['type' => 'page', 'target_page' => '42', 'expected' => '42'],
        ['type' => 'route', 'target_route' => 'site.cases.index', 'expected' => 'site.cases.index'],
        ['type' => 'url', 'target_url' => 'https://example.com', 'expected' => 'https://example.com'],
        ['type' => 'anchor', 'target_anchor' => '#contact', 'expected' => '#contact'],
    ];

    foreach ($cases as $case) {
        $expected = $case['expected'];
        unset($case['expected']);

        $result = SiteMenuItemResource::collapseTarget($case);

        expect($result['target'])->toBe($expected);

        // 未选中的字段不应残留在写入数据里
        foreach (SiteMenuItemResource::TARGET_FIELDS as $field) {
            expect($result)->not->toHaveKey($field);
        }
    }
});

/**
 * expandTarget：库里的 target 回填到对应表单字段
 */
it('target 列展开到对应表单字段', function () {
    expect(SiteMenuItemResource::expandTarget(['type' => 'page', 'target' => '7'])['target_page'])->toBe('7')
        ->and(SiteMenuItemResource::expandTarget(['type' => 'url', 'target' => 'https://a.test'])['target_url'])->toBe('https://a.test')
        ->and(SiteMenuItemResource::expandTarget(['type' => 'anchor', 'target' => '#z'])['target_anchor'])->toBe('#z');
});

/**
 * 未知 type 时 target 收敛为 null，不会把上一次的值带过去
 */
it('未知 type 收敛出空 target', function () {
    $result = SiteMenuItemResource::collapseTarget([
        'type'        => 'whatever',
        'target_page' => '42',
    ]);

    expect($result['target'])->toBeNull();
});
