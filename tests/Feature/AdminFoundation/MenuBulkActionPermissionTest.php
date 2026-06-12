<?php

use FilamentAdmin\Filament\Resources\Menus\MenuResource;
use FilamentAdmin\Models\AdminUser;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

it('拥有 update_menu 权限的管理员 can 判定为 true', function () {
    Permission::create([
        'name'       => 'update_menu',
        'guard_name' => 'admin',
    ]);
    $role = Role::create([
        'name'       => '菜单编辑员',
        'guard_name' => 'admin',
    ]);
    $role->givePermissionTo('update_menu');

    $admin = AdminUser::factory()->create();
    $admin->assignRole($role);

    $this->actingAs($admin, 'admin');

    expect(auth('admin')->user()->can('update_menu'))->toBeTrue();
});

it('不拥有 update_menu 权限的管理员 can 判定为 false', function () {
    $role = Role::create([
        'name'       => '只读管理员',
        'guard_name' => 'admin',
    ]);

    $admin = AdminUser::factory()->create();
    $admin->assignRole($role);

    $this->actingAs($admin, 'admin');

    expect(auth('admin')->user()->can('update_menu'))->toBeFalse();
});

it('MenuResource 批量操作定义中包含 update_menu 权限可见性检查', function () {
    expect(
        file_get_contents(
            base_path('packages/filament-admin/src/Filament/Resources/Menus/MenuResource.php')
        )
    )->toContain('update_menu');
});
