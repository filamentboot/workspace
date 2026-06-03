<?php

use FilamentAdmin\Models\AdminUser;
use FilamentAdmin\Models\Menu;
use FilamentAdmin\Services\AdminNavigationBuilder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

it('无权限菜单不会出现在导航中', function () {
    Permission::create([
        'name'       => 'view_secret_menu',
        'guard_name' => 'admin',
    ]);
    $role = Role::create([
        'name'       => '普通管理员',
        'guard_name' => 'admin',
    ]);
    $user = AdminUser::factory()->create();
    $user->assignRole($role);

    // 先建顶层菜单组（parent_id=0），再把子菜单挂在该组下
    $group = Menu::factory()->create([
        'title'      => '功能菜单',
        'parent_id'  => 0,
        'url'        => null,
        'route_name' => null,
        'sort'       => 0,
    ]);

    Menu::factory()->create([
        'title'           => '公开菜单',
        'parent_id'       => $group->id,
        'permission_name' => null,
        'url'             => '/admin/public',
        'sort'            => 1,
    ]);
    Menu::factory()->create([
        'title'           => '秘密菜单',
        'parent_id'       => $group->id,
        'permission_name' => 'view_secret_menu',
        'url'             => '/admin/secret',
        'sort'            => 2,
    ]);

    $groups = app(AdminNavigationBuilder::class)->build($user);
    $labels = collect($groups)
        ->flatMap(fn ($group) => $group->getItems())
        ->map(fn ($item) => $item->getLabel())
        ->all();

    expect($labels)->toContain('公开菜单')
        ->and($labels)->not->toContain('秘密菜单');
});

it('无效路由且没有备用地址的菜单不会出现在导航中', function () {
    $user = AdminUser::factory()->create();

    Menu::factory()->create([
        'title'      => '失效菜单',
        'route_name' => 'missing.route',
        'url'        => null,
    ]);

    $groups = app(AdminNavigationBuilder::class)->build($user);
    $labels = collect($groups)
        ->flatMap(fn ($group) => $group->getItems())
        ->map(fn ($item) => $item->getLabel())
        ->all();

    expect($labels)->not->toContain('失效菜单');
});

it('会合并插件市场内置导航入口', function () {
    $user = AdminUser::factory()->create();

    $groups = app(AdminNavigationBuilder::class)->build($user);

    $groupLabels = collect($groups)
        ->map(fn ($group) => $group->getLabel())
        ->all();

    $pluginMarketItems = collect($groups)
        ->first(fn ($group) => $group->getLabel() === '插件市场')
        ?->getItems() ?? [];

    $pluginMarketLabels = collect($pluginMarketItems)
        ->map(fn ($item) => $item->getLabel())
        ->all();

    expect($groupLabels)->toContain('插件市场')
        ->and($pluginMarketLabels)->toContain('官方市场')
        ->and($pluginMarketLabels)->toContain('扩展清单');
});
