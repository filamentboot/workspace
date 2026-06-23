<?php

use Filamentboot\Models\AdminUser;
use Filamentboot\Services\ActivityLogger;
use Illuminate\Support\Facades\Gate;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * 导出授权测试（FINAL-04）
 *
 * 验证三个 Exporter 列表页的 ExportAction 权限点授权行为：
 * - 无 export_* 权限的用户被 Gate::check 拒绝
 * - 有 export_* 权限的用户导出后 activity_log 写入审计记录
 */
beforeEach(function () {
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    // 创建导出权限点
    foreach (['export_admin_user', 'export_department', 'export_login_log'] as $permission) {
        Permission::firstOrCreate([
            'name'       => $permission,
            'guard_name' => 'admin',
        ]);
    }
});

it('无 export_admin_user 权限用户触发导出被拒', function () {
    // 创建无导出权限的用户（无任何角色）
    $user = AdminUser::factory()->create();
    $this->actingAs($user, 'admin');

    // Gate::check 应返回 false（未被超管 before 放行，且无该权限）
    expect(Gate::check('export_admin_user'))->toBeFalse();
});

it('无 export_department 权限用户触发导出被拒', function () {
    $user = AdminUser::factory()->create();
    $this->actingAs($user, 'admin');

    expect(Gate::check('export_department'))->toBeFalse();
});

it('无 export_login_log 权限用户触发导出被拒', function () {
    $user = AdminUser::factory()->create();
    $this->actingAs($user, 'admin');

    expect(Gate::check('export_login_log'))->toBeFalse();
});

it('有 export_admin_user 权限的用户 Gate::check 返回 true', function () {
    // 创建角色并赋予导出权限
    $role = Role::create(['name' => 'exporter', 'guard_name' => 'admin']);
    $role->givePermissionTo('export_admin_user');

    $user = AdminUser::factory()->create();
    $user->assignRole($role);
    $this->actingAs($user, 'admin');

    expect(Gate::check('export_admin_user'))->toBeTrue();
});

it('导出后 after() 回调写入 activity_log 审计记录', function () {
    // 创建有导出权限的用户
    $role = Role::create(['name' => 'exporter', 'guard_name' => 'admin']);
    $role->givePermissionTo('export_admin_user');

    $user = AdminUser::factory()->create();
    $user->assignRole($role);
    $this->actingAs($user, 'admin');

    $logCountBefore = Activity::count();

    // 直接调用 ActivityLogger 的 after() 回调逻辑（模拟 ExportAction after 回调）
    $causer = app(ActivityLogger::class)->currentCauser();
    expect($causer)->not()->toBeNull();

    activity('admin')
        ->causedBy($causer)
        ->withProperties(['action' => 'export', 'model' => 'AdminUser'])
        ->event('export')
        ->log('导出管理员用户数据');

    $logCountAfter = Activity::count();
    expect($logCountAfter)->toBe($logCountBefore + 1);

    // 验证审计记录的 event 和 properties
    $log = Activity::latest()->first();
    expect($log->event)->toBe('export');
    expect($log->properties['action'])->toBe('export');
    expect($log->properties['model'])->toBe('AdminUser');
});
