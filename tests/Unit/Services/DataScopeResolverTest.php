<?php

use App\Enums\DataScope;
use App\Models\AdminUser;
use App\Models\Department;
use App\Models\RoleDataScope;
use App\Services\DataScopeResolver;
use Spatie\Permission\Models\Role;

it('超级管理员拥有全部数据范围', function () {
    $role = Role::create([
        'name'       => config('filament-admin.super_admin_role'),
        'guard_name' => 'admin',
    ]);

    $admin = AdminUser::factory()->create();
    $admin->assignRole($role);

    $resolved = app(DataScopeResolver::class)->resolve($admin);

    expect($resolved['is_all'])->toBeTrue()
        ->and($resolved['department_ids'])->toBe([])
        ->and($resolved['admin_user_ids'])->toBe([]);
});

it('本部门及下级部门范围会展开部门树', function () {
    $root  = Department::factory()->create();
    $child = Department::factory()->create(['parent_id' => $root->id]);

    $role = Role::create([
        'name'       => '运营经理',
        'guard_name' => 'admin',
    ]);

    RoleDataScope::create([
        'role_id'        => $role->id,
        'scope'          => DataScope::DepartmentAndChildren,
        'department_ids' => null,
    ]);

    $admin = AdminUser::factory()->create([
        'department_id' => $root->id,
    ]);
    $admin->assignRole($role);

    $resolved = app(DataScopeResolver::class)->resolve($admin->fresh());

    expect($resolved['is_all'])->toBeFalse()
        ->and($resolved['department_ids'])->toBe([$root->id, $child->id])
        ->and($resolved['admin_user_ids'])->toBe([]);
});

it('未配置数据范围的角色默认回落到仅本人', function () {
    $role = Role::create([
        'name'       => '客服',
        'guard_name' => 'admin',
    ]);

    $admin = AdminUser::factory()->create();
    $admin->assignRole($role);

    $resolved = app(DataScopeResolver::class)->resolve($admin->fresh());

    expect($resolved['is_all'])->toBeFalse()
        ->and($resolved['department_ids'])->toBe([])
        ->and($resolved['admin_user_ids'])->toBe([$admin->id]);
});
