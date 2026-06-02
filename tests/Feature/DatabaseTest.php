<?php

use App\Models\AdminUser;
use Database\Seeders\AdminFoundationMenuSeeder;
use Database\Seeders\AdminFoundationPermissionSeeder;
use Database\Seeders\SuperAdminSeeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * 数据库连接测试
 *
 * 验证测试数据库（MySQL）连接正常，
 * 可以正常读写数据。
 */
test('can create and query records in test database', function () {
    DB::table('migrations')->insert([
        'migration' => 'test_migration_verify',
        'batch'     => 999,
    ]);

    expect(DB::table('migrations')->where('migration', 'test_migration_verify')->exists())
        ->toBeTrue();
});

// 注意：以下测试依赖 RefreshDatabase（在 tests/Pest.php 中已全局 apply）

it('执行核心 Seeder 后超级管理员可以登录后台', function () {
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    // 运行核心 Seeder（不含 AdminUserSeeder，因为它有 local 环境判断）
    $this->seed(AdminFoundationPermissionSeeder::class);
    $this->seed(SuperAdminSeeder::class);
    $this->seed(AdminFoundationMenuSeeder::class);

    app(PermissionRegistrar::class)->forgetCachedPermissions();

    // 验证超级管理员账号存在
    $admin = AdminUser::where('email', 'admin@example.com')->first();
    expect($admin)->not->toBeNull();

    // 验证超级管理员角色已分配
    $superAdminRole = config('filament-admin.super_admin_role', 'super_admin');
    expect($admin->hasRole($superAdminRole, 'admin'))->toBeTrue();

    // 验证可以通过 admin guard 认证
    $authenticated = auth('admin')->attempt([
        'email'    => 'admin@example.com',
        'password' => 'password',
    ]);

    expect($authenticated)->toBeTrue();

    $user = auth('admin')->user();
    expect($user)->not->toBeNull()
        ->and($user->email)->toBe('admin@example.com');
});

it('执行核心 Seeder 后基础权限点已存在', function () {
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $this->seed(AdminFoundationPermissionSeeder::class);

    app(PermissionRegistrar::class)->forgetCachedPermissions();

    // 验证至少存在 admin guard 下的 Permission 记录
    $count = Permission::where('guard_name', 'admin')->count();
    expect($count)->toBeGreaterThan(0);
});

it('执行核心 Seeder 后 super_admin 角色已在 admin guard 下创建', function () {
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $this->seed(AdminFoundationPermissionSeeder::class);
    $this->seed(SuperAdminSeeder::class);

    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $roleName = config('filament-admin.super_admin_role', 'super_admin');
    $role     = Role::where('name', $roleName)->where('guard_name', 'admin')->first();

    expect($role)->not->toBeNull();
});
