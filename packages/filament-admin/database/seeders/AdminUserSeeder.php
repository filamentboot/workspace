<?php

namespace Database\Seeders;

use FilamentAdmin\Models\AdminUser;
use Illuminate\Database\Seeder;

/**
 * 管理员用户种子数据
 *
 * 仅在 local 环境下创建默认管理员和测试账号。
 */
class AdminUserSeeder extends Seeder
{
    /**
     * 运行种子数据
     */
    public function run(): void
    {
        // 创建默认管理员（仅开发环境）
        if (app()->environment('local')) {
            AdminUser::firstOrCreate(
                ['email' => 'admin@example.com'],
                [
                    'account'           => 'admin',
                    'nickname'          => '系统管理员',
                    'password'          => 'password',
                    'email_verified_at' => now(),
                ]
            );

            $this->command->info('已创建默认管理员账号：');
            $this->command->info('  用户名: admin');
            $this->command->info('  邮箱: admin@example.com');
            $this->command->info('  密码: password');

            // 创建额外测试账号
            AdminUser::factory()->count(5)->create();
            $this->command->info('已创建 5 个测试管理员账号');
        }
    }
}
