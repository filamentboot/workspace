<?php

use Filamentboot\Models\AdminUser;
use Filamentboot\Models\Menu;
use Filamentboot\Services\AdminNavigationBuilder;
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

it('Page 路由菜单只在自身页面高亮，不会被同级页面串台点亮', function () {
    $user = AdminUser::factory()->create();

    $group = Menu::factory()->create([
        'title'      => '系统配置',
        'parent_id'  => 0,
        'url'        => null,
        'route_name' => null,
        'sort'       => 1,
    ]);

    // 两个同属 filament.admin.pages.* 前缀的 Page 菜单
    Menu::factory()->create([
        'title'           => '基础配置',
        'parent_id'       => $group->id,
        'permission_name' => null,
        'route_name'      => 'filament.admin.pages.settings.general',
        'url'             => null,
        'sort'            => 1,
    ]);
    Menu::factory()->create([
        'title'           => '上传配置',
        'parent_id'       => $group->id,
        'permission_name' => null,
        'route_name'      => 'filament.admin.pages.settings.upload',
        'url'             => null,
        'sort'            => 2,
    ]);

    // 把当前请求指向"基础配置"页面
    $request = Request::create(route('filament.admin.pages.settings.general'));
    $request->setRouteResolver(fn () => Route::getRoutes()->match($request));
    app()->instance('request', $request);

    $activeLabels = collect(app(AdminNavigationBuilder::class)->build($user))
        ->flatMap(fn ($group) => $group->getItems())
        ->filter(fn ($item) => $item->isActive())
        ->map(fn ($item) => $item->getLabel())
        ->values()
        ->all();

    expect($activeLabels)->toBe(['基础配置']);
});

it('Resource 路由菜单在自身的 create / edit 子页面同样保持高亮', function () {
    $user = AdminUser::factory()->create();

    $group = Menu::factory()->create([
        'title'      => '系统管理',
        'parent_id'  => 0,
        'url'        => null,
        'route_name' => null,
        'sort'       => 1,
    ]);

    Menu::factory()->create([
        'title'           => '管理员管理',
        'parent_id'       => $group->id,
        'permission_name' => null,
        'route_name'      => 'filament.admin.resources.admin-users.index',
        'url'             => null,
        'sort'            => 1,
    ]);

    // 当前请求指向该资源的新建页
    $request = Request::create(route('filament.admin.resources.admin-users.create'));
    $request->setRouteResolver(fn () => Route::getRoutes()->match($request));
    app()->instance('request', $request);

    $activeLabels = collect(app(AdminNavigationBuilder::class)->build($user))
        ->flatMap(fn ($group) => $group->getItems())
        ->filter(fn ($item) => $item->isActive())
        ->map(fn ($item) => $item->getLabel())
        ->values()
        ->all();

    expect($activeLabels)->toBe(['管理员管理']);
});
