<?php

use App\Enums\DataScope;
use App\Models\RoleDataScope;
use Spatie\Permission\Models\Role;

it('角色数据权限保存范围和指定部门', function () {
    $role = Role::create([
        'name'       => '运营',
        'guard_name' => 'admin',
    ]);

    $scope = RoleDataScope::factory()->create([
        'role_id'        => $role->id,
        'scope'          => DataScope::CustomDepartments,
        'department_ids' => [1, 2],
    ]);

    expect($scope->role->is($role))->toBeTrue()
        ->and($scope->scope)->toBe(DataScope::CustomDepartments)
        ->and($scope->department_ids)->toBe([1, 2]);
});
