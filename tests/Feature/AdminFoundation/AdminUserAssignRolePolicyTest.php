<?php

use FilamentAdmin\Database\Seeders\AdminFoundationPermissionSeeder;
use FilamentAdmin\Models\AdminUser;
use Illuminate\Support\Facades\Gate;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->seed(AdminFoundationPermissionSeeder::class);
});

/**
 * 用例 A：拥有 assign_role_admin_user 权限的管理员，Gate::assignRole 应为 true
 *
 * 验证 AdminUserPolicy::assignRole 被 Gate 正确路由，权限检查工作正常。
 * Policy 方法签名：assignRole(Authenticatable $user, Model $model)
 * Gate 调用方式：Gate::forUser($admin)->allows('assignRole', $targetAdmin)
 */
it('拥有 assign_role_admin_user 权限的管理员通过 Gate assignRole 应返回 true', function () {
    $role = Role::create([
        'name'       => '角色管理员',
        'guard_name' => 'admin',
    ]);
    $role->givePermissionTo('assign_role_admin_user');

    $admin = AdminUser::factory()->create();
    $admin->assignRole($role);

    $targetAdmin = AdminUser::factory()->create();

    app(PermissionRegistrar::class)->forgetCachedPermissions();

    // 通过 Gate::forUser 验证 assignRole Policy 方法被命中（传 Model 实例）
    expect(Gate::forUser($admin)->allows('assignRole', $targetAdmin))->toBeTrue();
});

/**
 * 用例 B：不拥有 assign_role_admin_user 权限的管理员，Gate::assignRole 应为 false
 *
 * 验证无权限者无法通过 Policy 校验，字段可见性受限。
 */
it('不拥有 assign_role_admin_user 权限的管理员通过 Gate assignRole 应返回 false', function () {
    $role = Role::create([
        'name'       => '普通管理员',
        'guard_name' => 'admin',
    ]);
    // 只给查看权限，不给 assign_role_admin_user
    $role->givePermissionTo([
        'view_any_admin_user',
        'view_admin_user',
        'update_admin_user',
    ]);

    $admin = AdminUser::factory()->create();
    $admin->assignRole($role);

    $targetAdmin = AdminUser::factory()->create();

    app(PermissionRegistrar::class)->forgetCachedPermissions();

    // 无 assign_role_admin_user 权限时，Gate::assignRole 应返回 false
    expect(Gate::forUser($admin)->allows('assignRole', $targetAdmin))->toBeFalse();
});
