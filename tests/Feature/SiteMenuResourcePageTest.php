<?php

use Filament\Facades\Filament;
use Filamentboot\FilamentbootSite\Filament\Resources\SiteMenuItemResource;
use Filamentboot\FilamentbootSite\Filament\Resources\SiteMenuItemResource\Pages\SiteMenuItemTree;
use Filamentboot\FilamentbootSite\Filament\Resources\SiteMenuResource;
use Filamentboot\FilamentbootSite\Filament\Resources\SiteMenuResource\Pages\ListSiteMenus;
use Filamentboot\FilamentbootSite\Models\SiteMenu;
use Filamentboot\FilamentbootSite\Models\SiteMenuItem;
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
