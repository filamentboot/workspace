<?php

use Database\Seeders\AdminFoundationPermissionSeeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

it('基础管理权限点被种子创建', function () {
    $this->seed(AdminFoundationPermissionSeeder::class);

    foreach ([
        'view_any_admin_user',
        'reset_password_admin_user',
        'assign_role_admin_user',
        'view_any_login_log',
        'view_any_menu',
        'reorder_menu',
        'view_any_department',
        'reorder_department',
        'view_any_role_data_scope',
        'view_any_activity_log',
    ] as $permission) {
        expect(
            Permission::query()
                ->where('guard_name', 'admin')
                ->where('name', $permission)
                ->exists()
        )->toBeTrue();
    }
});
