<?php

namespace Database\Seeders;

use Filamentboot\Database\Seeders\AdminFoundationMenuSeeder;
use Filamentboot\Database\Seeders\AdminFoundationPermissionSeeder;
use Filamentboot\Database\Seeders\AdminUserSeeder;
use Filamentboot\Database\Seeders\DemoSeeder;
use Filamentboot\Database\Seeders\SuperAdminSeeder;
use Illuminate\Database\Seeder;

/**
 * 数据库种子入口
 */
class DatabaseSeeder extends Seeder
{
    /**
     * 运行所有种子数据
     */
    public function run(): void
    {
        $seeders = [
            AdminUserSeeder::class,
            AdminFoundationPermissionSeeder::class,
            SuperAdminSeeder::class,
            AdminFoundationMenuSeeder::class,
        ];

        // 仅演示环境播种演示账号，避免生产环境创建 demo 账号
        if (config('app.demo')) {
            $seeders[] = DemoSeeder::class;
        }

        $this->call($seeders);
    }
}
