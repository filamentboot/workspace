<?php

namespace FilamentAdmin\Database\Seeders;

use FilamentAdmin\Models\AdminUser;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * 演示账号种子
 *
 * 创建演示站专用账号 demo@example.com / demo123，
 * 分配 super_admin 角色以展示后台全貌。
 * 写操作由 Plan 05-02 的 Gate::before 演示拒绝分支屏蔽。
 *
 * 幂等：重复运行不产生重复数据（firstOrCreate 保证）。
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        // 清除 Spatie Permission 缓存，确保角色创建生效
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $roleName = config('filament-admin.super_admin_role', 'super_admin');

        // 取得 super_admin 角色（不新建独立 demo 角色，复用超管展示全貌）
        $role = Role::firstOrCreate([
            'name'       => $roleName,
            'guard_name' => 'admin',
        ]);

        // 创建演示账号（password 字段由 AdminUser 的 hashed cast 自动哈希，传明文即可）
        $demo = AdminUser::firstOrCreate(
            ['email' => 'demo@example.com'],
            [
                'account'           => 'demo',
                'nickname'          => '演示账号',
                'password'          => 'demo123',
                'email_verified_at' => now(),
            ]
        );

        $demo->assignRole($role);

        $this->command->info('演示账号已创建：demo@example.com / demo123');
    }
}
