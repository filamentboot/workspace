<?php

use App\Enums\DataScope;
use App\Filament\Resources\RoleDataScopes\RoleDataScopeResource;
use App\Models\AdminUser;
use App\Models\Department;
use App\Models\RoleDataScope;
use App\Services\DataScopeResolver;
use Spatie\Permission\Models\Role;

it('超级管理员可以访问数据权限列表', function () {
    $role = Role::create([
        'name'       => config('filament-admin.super_admin_role'),
        'guard_name' => 'admin',
    ]);
    $admin = AdminUser::factory()->create();
    $admin->assignRole($role);

    $this->actingAs($admin, 'admin')
        ->get(RoleDataScopeResource::getUrl('index'))
        ->assertSuccessful();
});

it('指定部门数据范围只返回配置部门', function () {
    $department = Department::factory()->create();
    $role       = Role::create(['name' => '指定部门角色', 'guard_name' => 'admin']);
    $user       = AdminUser::factory()->create();
    $user->assignRole($role);

    RoleDataScope::factory()->create([
        'role_id'        => $role->id,
        'scope'          => DataScope::CustomDepartments,
        'department_ids' => [$department->id],
    ]);

    $scope = app(DataScopeResolver::class)->resolve($user);

    expect($scope['department_ids'])->toBe([$department->id]);
});
