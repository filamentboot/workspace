<?php

use AlizHarb\ActivityLog\Resources\ActivityLogs\ActivityLogResource;
use FilamentAdmin\Models\AdminUser;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

it('无权限管理员访问操作日志页面被拦截', function () {
    $admin = AdminUser::factory()->create();

    $this->actingAs($admin, 'admin');

    $response = $this->get(ActivityLogResource::getUrl(panel: 'admin'));

    $response->assertForbidden();
});

it('拥有 view_any_activity_log 权限的管理员可以访问操作日志页面', function () {
    Permission::firstOrCreate([
        'name'       => 'view_any_activity_log',
        'guard_name' => 'admin',
    ]);

    $role = Role::create([
        'name'       => 'auditor',
        'guard_name' => 'admin',
    ]);

    $role->givePermissionTo('view_any_activity_log');

    $admin = AdminUser::factory()->create();
    $admin->assignRole($role);

    $this->actingAs($admin, 'admin');

    $response = $this->get(ActivityLogResource::getUrl(panel: 'admin'));

    $response->assertSuccessful();
});
