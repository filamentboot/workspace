<?php

use Filamentboot\Database\Seeders\SuperAdminSeeder;
use Filamentboot\Models\AdminUser;
use Illuminate\Support\Facades\Artisan;

/**
 * demo:reset 命令测试
 *
 * 验证：护栏（非演示环境返回 FAILURE 且不动数据）、
 * 重置行为（--force 执行后 demo 账号存在、roles/permissions/menus 非空、
 * login_logs/activity_log 清空）、以及 cron 配置。
 */
it('非演示环境执行 demo:reset 返回 FAILURE', function () {
    // 确保非演示环境
    config(['app.demo' => false]);

    $exitCode = Artisan::call('demo:reset');

    expect($exitCode)->not->toBe(0);
});

it('非演示环境执行 demo:reset 不修改数据库数据', function () {
    // 播种初始数据并记录条数
    $this->seed(SuperAdminSeeder::class);
    $countBefore = AdminUser::count();

    // 确保非演示环境
    config(['app.demo' => false]);

    Artisan::call('demo:reset');

    $countAfter = AdminUser::count();

    expect($countAfter)->toBe($countBefore);
});

it('带 --force 执行 demo:reset 后演示账号存在', function () {
    $exitCode = Artisan::call('demo:reset', ['--force' => true]);

    expect($exitCode)->toBe(0)
        ->and(AdminUser::where('email', 'demo@example.com')->exists())->toBeTrue();
});

it('带 --force 执行 demo:reset 后 login_logs 表为空', function () {
    // 先插入一条登录日志（字段与 login_logs 迁移对齐）
    DB::table('login_logs')->insert([
        'admin_user_id' => null,
        'username'      => 'test',
        'ip_address'    => '127.0.0.1',
        'status'        => 'success',
        'created_at'    => now(),
    ]);

    Artisan::call('demo:reset', ['--force' => true]);

    expect(DB::table('login_logs')->count())->toBe(0);
});

it('带 --force 执行 demo:reset 后 activity_log 表为空', function () {
    // 先插入一条活动日志
    DB::table('activity_log')->insert([
        'log_name'     => 'default',
        'description'  => 'test',
        'subject_type' => 'test',
        'subject_id'   => 1,
        'event'        => 'test',
        'causer_type'  => null,
        'causer_id'    => null,
        'properties'   => '{}',
        'batch_uuid'   => null,
        'created_at'   => now(),
        'updated_at'   => now(),
    ]);

    Artisan::call('demo:reset', ['--force' => true]);

    expect(DB::table('activity_log')->count())->toBe(0);
});

it('带 --force 执行 demo:reset 后 roles 表非空', function () {
    Artisan::call('demo:reset', ['--force' => true]);

    expect(DB::table('roles')->count())->toBeGreaterThan(0);
});

it('routes/console.php 中已注册 demo:reset 的 dailyAt 04:00 调度', function () {
    $consoleFile = base_path('routes/console.php');
    $content     = file_get_contents($consoleFile);

    expect($content)->toContain('DemoReset')
        ->and($content)->toContain("dailyAt('04:00')");
});
