<?php

use FilamentAdmin\Models\AdminUser;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

it('超级管理员可以访问 Shield 自带的角色管理页面', function () {
    $role = Role::create([
        'name'       => config('filament-admin.super_admin_role'),
        'guard_name' => 'admin',
    ]);
    $admin = AdminUser::factory()->create();
    $admin->assignRole($role);

    $this->actingAs($admin, 'admin');

    $response = $this->get('/admin/shield/roles');

    $response->assertSuccessful();
});

it('无权限的管理员访问角色管理页面被拦截', function () {
    $admin = AdminUser::factory()->create();
    $this->actingAs($admin, 'admin');

    $response = $this->get('/admin/shield/roles');

    $response->assertForbidden();
});
