<?php

use App\Models\AdminUser;
use App\Models\Menu;
use App\Services\AdminNavigationBuilder;
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

    Menu::factory()->create([
        'title'           => '公开菜单',
        'permission_name' => null,
        'url'             => '/admin/public',
        'sort'            => 1,
    ]);
    Menu::factory()->create([
        'title'           => '秘密菜单',
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
