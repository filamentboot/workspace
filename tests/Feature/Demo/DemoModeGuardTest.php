<?php

use FilamentAdmin\Database\Seeders\DemoSeeder;
use FilamentAdmin\Database\Seeders\SuperAdminSeeder;
use FilamentAdmin\Models\AdminUser;
use Illuminate\Support\Facades\Gate;
use Spatie\Permission\Models\Role;

/**
 * DemoMode Gate::before 演示写操作屏蔽测试
 *
 * 验证：
 * 1. 演示账号对写类 ability 被拒绝
 * 2. 演示账号对读类 ability 仍被放行
 * 3. 演示账号即使挂 super_admin 角色，写操作仍被拒（演示判定先于超管放行）
 * 4. 非演示 super_admin 用户权限不受演示分支影响
 */

beforeEach(function () {
    $this->seed(DemoSeeder::class);
    $this->seed(SuperAdminSeeder::class);
});

it('演示账号对 delete ability 被拒绝', function () {
    $demo = AdminUser::where('email', 'demo@example.com')->first();
    $targetUser = AdminUser::where('email', 'admin@example.com')->first();

    expect(Gate::forUser($demo)->denies('delete', $targetUser))->toBeTrue();
});

it('演示账号对 create ability 被拒绝', function () {
    $demo = AdminUser::where('email', 'demo@example.com')->first();

    expect(Gate::forUser($demo)->denies('create', AdminUser::class))->toBeTrue();
});

it('演示账号对 update ability 被拒绝', function () {
    $demo = AdminUser::where('email', 'demo@example.com')->first();
    $role = Role::where('name', 'super_admin')->first();

    expect(Gate::forUser($demo)->denies('update', $role))->toBeTrue();
});

it('演示账号对 deleteAny ability 被拒绝', function () {
    $demo = AdminUser::where('email', 'demo@example.com')->first();

    expect(Gate::forUser($demo)->denies('deleteAny', Role::class))->toBeTrue();
});

it('演示账号对 viewAny ability 仍被放行（读操作不拦截）', function () {
    $demo = AdminUser::where('email', 'demo@example.com')->first();

    // 演示账号挂 super_admin，读操作走超管放行返回 true
    expect(Gate::forUser($demo)->allows('viewAny', AdminUser::class))->toBeTrue();
});

it('[BLOCKING] 演示账号挂 super_admin 时 deleteAny Role 仍被拒绝（演示判定先于超管放行）', function () {
    $demo = AdminUser::where('email', 'demo@example.com')->first();

    // 确认演示账号已挂 super_admin（由 DemoSeeder 分配）
    expect($demo->hasRole('super_admin'))->toBeTrue();

    // 即使挂了 super_admin，写操作仍应被演示分支拦截
    expect(Gate::forUser($demo)->denies('deleteAny', Role::class))->toBeTrue();
});

it('非演示 super_admin 用户对 delete ability 正常放行（不受演示分支影响）', function () {
    $admin = AdminUser::where('email', 'admin@example.com')->first();

    // 确认是 super_admin 但不是演示账号
    expect($admin->hasRole('super_admin'))->toBeTrue()
        ->and($admin->email)->not->toBe('demo@example.com');

    // 非演示用户的超管权限应正常放行
    expect(Gate::forUser($admin)->allows('delete', AdminUser::where('email', 'demo@example.com')->first()))->toBeTrue();
});
