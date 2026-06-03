<?php

use FilamentAdmin\Models\AdminUser;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

it('拥有 view_any_admin_user 权限的角色可以列表查看管理员', function () {
    Permission::firstOrCreate([
        'name'       => 'view_any_admin_user',
        'guard_name' => 'admin',
    ]);
    $role = Role::create(['name' => 'editor', 'guard_name' => 'admin']);
    $role->givePermissionTo('view_any_admin_user');

    $admin = AdminUser::factory()->create();
    $admin->assignRole($role);

    $this->actingAs($admin, 'admin');

    expect($admin->can('viewAny', AdminUser::class))->toBeTrue();
});

it('没有权限的管理员无法创建其他管理员', function () {
    $admin = AdminUser::factory()->create();
    $this->actingAs($admin, 'admin');

    expect($admin->can('create', AdminUser::class))->toBeFalse();
});

it('拥有 update 权限但无 delete 权限的角色可以更新但不能删除', function () {
    Permission::firstOrCreate(['name' => 'update_admin_user', 'guard_name' => 'admin']);
    $role = Role::create(['name' => 'updater', 'guard_name' => 'admin']);
    $role->givePermissionTo('update_admin_user');

    $admin = AdminUser::factory()->create();
    $admin->assignRole($role);
    $target = AdminUser::factory()->create();

    $this->actingAs($admin, 'admin');

    expect($admin->can('update', $target))->toBeTrue()
        ->and($admin->can('delete', $target))->toBeFalse();
});
