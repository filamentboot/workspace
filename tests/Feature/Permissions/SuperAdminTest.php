<?php

use FilamentAdmin\Models\AdminUser;
use Illuminate\Support\Facades\Gate;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

it('超级管理员可绕过所有权限检查', function () {
    $role = Role::create([
        'name'       => config('filament-admin.super_admin_role'),
        'guard_name' => 'admin',
    ]);
    $admin = AdminUser::factory()->create();
    $admin->assignRole($role);

    $this->actingAs($admin, 'admin');

    expect(Gate::allows('viewAny', AdminUser::class))->toBeTrue()
        ->and(Gate::allows('create', AdminUser::class))->toBeTrue()
        ->and(Gate::allows('any.random.ability', new AdminUser))->toBeTrue();
});

it('普通管理员未分配权限时无法通过 Gate', function () {
    $admin = AdminUser::factory()->create();
    $this->actingAs($admin, 'admin');

    // 没有任何角色和权限，Gate::before 返回 null（不拦截），落入 Policy 默认拒绝
    expect(Gate::allows('viewAny', AdminUser::class))->toBeFalse();
});
