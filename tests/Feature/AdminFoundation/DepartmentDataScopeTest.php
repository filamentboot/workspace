<?php

use Filamentboot\Filament\Resources\Departments\DepartmentResource;
use Filamentboot\Models\AdminUser;
use Filamentboot\Models\Department;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

/**
 * 用例 A：超级管理员看全部部门
 *
 * 部门树：A(根) → B(A 子) → C(B 子)，平行树 X(根) → Y(X 子)
 * 共 5 个部门，超管应全部可见。
 */
it('超级管理员可以看到所有部门', function () {
    // 构造部门树
    $deptA = Department::factory()->create(['name' => '部门A', 'parent_id' => null]);
    $deptB = Department::factory()->create(['name' => '部门B', 'parent_id' => $deptA->id]);
    $deptC = Department::factory()->create(['name' => '部门C', 'parent_id' => $deptB->id]);
    $deptX = Department::factory()->create(['name' => '部门X', 'parent_id' => null]);
    $deptY = Department::factory()->create(['name' => '部门Y', 'parent_id' => $deptX->id]);

    // 创建超级管理员
    $superAdminRole = Role::create([
        'name'       => config('filamentboot.super_admin_role'),
        'guard_name' => 'admin',
    ]);
    $superAdmin = AdminUser::factory()->create();
    $superAdmin->assignRole($superAdminRole);

    // 以超管身份登录，验证看到全部 5 个部门
    $this->actingAs($superAdmin, 'admin');

    $ids = DepartmentResource::getEloquentQuery()->pluck('id');

    expect($ids)->toHaveCount(5)
        ->and($ids->contains($deptA->id))->toBeTrue()
        ->and($ids->contains($deptB->id))->toBeTrue()
        ->and($ids->contains($deptC->id))->toBeTrue()
        ->and($ids->contains($deptX->id))->toBeTrue()
        ->and($ids->contains($deptY->id))->toBeTrue();
});

/**
 * 用例 B：普通管理员（department_id = B）只看本部门 + 子树
 *
 * 期望结果：仅 {B, C}，不含 A / X / Y。
 */
it('普通管理员只能看到本部门及子部门', function () {
    // 构造部门树
    $deptA = Department::factory()->create(['name' => '部门A', 'parent_id' => null]);
    $deptB = Department::factory()->create(['name' => '部门B', 'parent_id' => $deptA->id]);
    $deptC = Department::factory()->create(['name' => '部门C', 'parent_id' => $deptB->id]);
    $deptX = Department::factory()->create(['name' => '部门X', 'parent_id' => null]);
    $deptY = Department::factory()->create(['name' => '部门Y', 'parent_id' => $deptX->id]);

    // 创建普通管理员，归属 B 部门
    $normalAdmin = AdminUser::factory()->create([
        'department_id' => $deptB->id,
    ]);

    // 以普通管理员身份登录
    $this->actingAs($normalAdmin, 'admin');

    $ids = DepartmentResource::getEloquentQuery()->pluck('id');

    // 仅能看到 B 和 C
    expect($ids)->toHaveCount(2)
        ->and($ids->contains($deptB->id))->toBeTrue()
        ->and($ids->contains($deptC->id))->toBeTrue()
        ->and($ids->contains($deptA->id))->toBeFalse()
        ->and($ids->contains($deptX->id))->toBeFalse()
        ->and($ids->contains($deptY->id))->toBeFalse();
});

/**
 * 用例 C：普通管理员无部门（department_id = null）返回空集合
 */
it('无部门的管理员看不到任何部门', function () {
    // 构造若干部门
    Department::factory()->create(['name' => '部门A', 'parent_id' => null]);
    Department::factory()->create(['name' => '部门X', 'parent_id' => null]);

    // 创建无部门管理员
    $noDeptAdmin = AdminUser::factory()->create([
        'department_id' => null,
    ]);

    // 以无部门管理员身份登录
    $this->actingAs($noDeptAdmin, 'admin');

    $ids = DepartmentResource::getEloquentQuery()->pluck('id');

    expect($ids)->toBeEmpty();
});
