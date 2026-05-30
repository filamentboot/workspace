<?php

use App\Filament\Resources\Menus\MenuResource;
use App\Models\AdminUser;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

it('超级管理员可以访问菜单规则列表', function () {
    $role = Role::create([
        'name'       => config('filament-admin.super_admin_role'),
        'guard_name' => 'admin',
    ]);
    $admin = AdminUser::factory()->create();
    $admin->assignRole($role);

    $this->actingAs($admin, 'admin')
        ->get(MenuResource::getUrl('index'))
        ->assertSuccessful();
});

it('拥有 reorder_menu 权限的管理员可以进行菜单排序', function () {
    Permission::create([
        'name'       => 'reorder_menu',
        'guard_name' => 'admin',
    ]);
    $role = Role::create([
        'name'       => '菜单管理员',
        'guard_name' => 'admin',
    ]);
    $role->givePermissionTo('reorder_menu');

    $admin = AdminUser::factory()->create();
    $admin->assignRole($role);

    $this->actingAs($admin, 'admin');

    expect(MenuResource::canReorder())->toBeTrue();
});
