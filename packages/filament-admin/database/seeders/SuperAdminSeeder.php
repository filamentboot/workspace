<?php

namespace Database\Seeders;

use FilamentAdmin\Models\AdminUser;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * 超级管理员种子
 *
 * 创建 super_admin 角色（admin guard）并创建首个超级管理员账号。
 * 默认账号：admin@example.com / password
 */
class SuperAdminSeeder extends Seeder
{
    public function run(): void
    {
        // 清除 Spatie Permission 缓存，确保角色创建生效
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $roleName = config('filament-admin.super_admin_role', 'super_admin');

        $role = Role::firstOrCreate([
            'name'       => $roleName,
            'guard_name' => 'admin',
        ]);

        $admin = AdminUser::firstOrCreate(
            ['email' => 'admin@example.com'],
            [
                'account'           => 'admin',
                'nickname'          => '超级管理员',
                'password'          => 'password', // AdminUser 的 hashed cast 会自动 hash
                'email_verified_at' => now(),
            ]
        );

        $admin->assignRole($role);

        $this->command->info("超级管理员已创建：{$admin->email} / password");
    }
}
