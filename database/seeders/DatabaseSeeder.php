<?php

namespace Database\Seeders;

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
        ]);
    }
}
