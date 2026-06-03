<?php

namespace Database\Seeders;

use FilamentAdmin\Database\Seeders\AdminFoundationMenuSeeder;
use FilamentAdmin\Database\Seeders\AdminFoundationPermissionSeeder;
use FilamentAdmin\Database\Seeders\AdminUserSeeder;
use FilamentAdmin\Database\Seeders\SuperAdminSeeder;
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
        $this->call([
            AdminUserSeeder::class,
            AdminFoundationPermissionSeeder::class,
            SuperAdminSeeder::class,
            AdminFoundationMenuSeeder::class,
        ]);
    }
}
