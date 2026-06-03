<?php

use FilamentAdmin\Filament\Resources\Departments\DepartmentResource;
use FilamentAdmin\Models\AdminUser;
use FilamentAdmin\Models\Department;
use FilamentAdmin\Services\DepartmentTree;
use Spatie\Permission\Models\Role;

it('超级管理员可以访问部门管理列表', function () {
    $role = Role::create([
        'name'       => config('filament-admin.super_admin_role'),
        'guard_name' => 'admin',
    ]);
    $admin = AdminUser::factory()->create();
    $admin->assignRole($role);

    $this->actingAs($admin, 'admin')
        ->get(DepartmentResource::getUrl('index'))
        ->assertSuccessful();
});

it('部门同级排序可更新', function () {
    $a = Department::factory()->create(['sort' => 1]);
    $b = Department::factory()->create(['sort' => 2]);

    $a->update(['sort' => 2]);
    $b->update(['sort' => 1]);

    expect(Department::query()->orderBy('sort')->pluck('id')->all())->toBe([$b->id, $a->id]);
});

it('部门不能移动到自己的下级', function () {
    $root  = Department::factory()->create();
    $child = Department::factory()->create(['parent_id' => $root->id]);

    expect(app(DepartmentTree::class)->wouldCreateCycle($root, $child))->toBeTrue();
});
