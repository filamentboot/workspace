<?php

use Filamentboot\Models\AdminUser;
use Filamentboot\Models\LoginLog;
use Filamentboot\Settings\LogSettings;
use Spatie\Activitylog\Models\Activity;

/**
 * 日志清理命令保留天数测试（读 LogSettings，--days 覆盖，0=永久保留）
 */

/**
 * 创建一条 Activity 记录的辅助函数
 *
 * @param  array<string, mixed>  $attributes
 */
function makeActivity(array $attributes = []): Activity
{
    /** @var Activity */
    return Activity::query()->create(array_merge([
        'log_name'     => 'admin',
        'description'  => '测试日志',
        'subject_type' => AdminUser::class,
        'subject_id'   => 1,
        'causer_type'  => AdminUser::class,
        'causer_id'    => 1,
        'event'        => 'test',
        'properties'   => [],
    ], $attributes));
}

// ===== CleanActivityLogs 测试 =====

it('清理操作日志：未传 --days 时从 LogSettings 读保留天数', function () {
    // 设置 LogSettings.activity_log_retention_days = 30
    $settings                              = app(LogSettings::class);
    $settings->activity_log_retention_days = 30;
    $settings->save();
    app()->forgetInstance(LogSettings::class);

    // 建两条记录：35 天前（应被删）和 10 天前（应保留）
    makeActivity(['created_at' => now()->subDays(35)]);
    makeActivity(['created_at' => now()->subDays(10)]);

    // 运行命令（不传 --days）
    $this->artisan('filamentboot:clean-activity-logs')
        ->assertSuccessful();

    // 断言仅删除 35 天前的那条，10 天前的保留
    expect(Activity::count())->toBe(1);
    expect(Activity::where('created_at', '<=', now()->subDays(35))->count())->toBe(0);
});

it('清理操作日志：传 --days=5 时覆盖 Settings 值', function () {
    // 设置 LogSettings.activity_log_retention_days = 30
    $settings                              = app(LogSettings::class);
    $settings->activity_log_retention_days = 30;
    $settings->save();
    app()->forgetInstance(LogSettings::class);

    // 建两条记录：35 天前和 10 天前（--days=5 覆盖后两条都应被删）
    makeActivity(['created_at' => now()->subDays(35)]);
    makeActivity(['created_at' => now()->subDays(10)]);

    // 运行命令，传 --days=5
    $this->artisan('filamentboot:clean-activity-logs', ['--days' => 5])
        ->assertSuccessful();

    // 断言两条都被删
    expect(Activity::count())->toBe(0);
});

it('清理操作日志：保留天数为 0 时永久保留不删除', function () {
    // 设置 LogSettings.activity_log_retention_days = 0（永久保留）
    $settings                              = app(LogSettings::class);
    $settings->activity_log_retention_days = 0;
    $settings->save();
    app()->forgetInstance(LogSettings::class);

    // 建一条很久以前的记录
    makeActivity(['created_at' => now()->subDays(9999)]);

    // 运行命令（不传 --days）
    $this->artisan('filamentboot:clean-activity-logs')
        ->assertSuccessful();

    // 断言记录未被删除（永久保留）
    expect(Activity::count())->toBe(1);
});

// ===== CleanLoginLogs 测试 =====

it('清理登录日志：未传 --days 时从 LogSettings 读保留天数', function () {
    // 设置 LogSettings.login_log_retention_days = 60
    $settings                           = app(LogSettings::class);
    $settings->login_log_retention_days = 60;
    $settings->save();
    app()->forgetInstance(LogSettings::class);

    // 建两条记录：70 天前（应被删）和 30 天前（应保留）
    $user = AdminUser::factory()->create();
    LoginLog::factory()->create([
        'admin_user_id' => $user->id,
        'created_at'    => now()->subDays(70),
    ]);
    LoginLog::factory()->create([
        'admin_user_id' => $user->id,
        'created_at'    => now()->subDays(30),
    ]);

    // 运行命令（不传 --days）
    $this->artisan('filamentboot:clean-login-logs')
        ->assertSuccessful();

    // 断言仅删除 70 天前的那条，30 天前的保留
    expect(LoginLog::count())->toBe(1);
});
