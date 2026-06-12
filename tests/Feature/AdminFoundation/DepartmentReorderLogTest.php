<?php

use FilamentAdmin\Filament\Resources\Departments\DepartmentResource;
use FilamentAdmin\Models\AdminUser;
use FilamentAdmin\Models\Department;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

it('部门排序后写入一条 reordered 活动日志', function () {
    // 创建超级管理员作为操作人
    $role = Role::create([
        'name'       => config('filament-admin.super_admin_role'),
        'guard_name' => 'admin',
    ]);
    $admin = AdminUser::factory()->create();
    $admin->assignRole($role);

    $this->actingAs($admin, 'admin');

    // 建两个部门
    $a = Department::factory()->create(['sort' => 1]);
    $b = Department::factory()->create(['sort' => 2]);

    // 模拟 Filament 拖拽排序：通过反射调用 protected static 方法
    $ref = new \ReflectionClass(DepartmentResource::class);

    // beforeReordering：记录排序前快照（a=sort1, b=sort2）
    $remember = $ref->getMethod('rememberReorderSnapshot');
    $remember->setAccessible(true);
    $remember->invoke(null, [$a->id, $b->id]);

    // 模拟 Filament 实际更新 sort（afterReordering 之前 DB 已被更新）
    $a->update(['sort' => 2]);
    $b->update(['sort' => 1]);

    // afterReordering：记录排序后日志（新顺序 b, a）
    $logActivity = $ref->getMethod('logReorderActivity');
    $logActivity->setAccessible(true);
    $logActivity->invoke(null, [$b->id, $a->id]);

    // 断言 activity_log 表新增了 reordered 事件
    $log = \Spatie\Activitylog\Models\Activity::query()
        ->where('event', 'reordered')
        ->where('subject_type', Department::class)
        ->latest()
        ->first();

    expect($log)->not->toBeNull()
        ->and($log->event)->toBe('reordered');

    // 断言 before/after 都包含 order 键
    $properties = $log->properties->toArray();
    expect($properties)->toHaveKey('before')
        ->and($properties)->toHaveKey('after')
        ->and($properties['before'])->toHaveKey('order')
        ->and($properties['after'])->toHaveKey('order');
});

it('无操作人时 logReorderActivity 不写日志', function () {
    // 未登录状态
    $a = Department::factory()->create(['sort' => 1]);
    $b = Department::factory()->create(['sort' => 2]);

    $initialCount = \Spatie\Activitylog\Models\Activity::query()
        ->where('event', 'reordered')
        ->count();

    $ref = new \ReflectionClass(DepartmentResource::class);

    $remember = $ref->getMethod('rememberReorderSnapshot');
    $remember->setAccessible(true);
    $remember->invoke(null, [$a->id, $b->id]);

    $logActivity = $ref->getMethod('logReorderActivity');
    $logActivity->setAccessible(true);
    $logActivity->invoke(null, [$b->id, $a->id]);

    $afterCount = \Spatie\Activitylog\Models\Activity::query()
        ->where('event', 'reordered')
        ->count();

    expect($afterCount)->toBe($initialCount);
});
