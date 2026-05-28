<?php

use App\Filament\Pages\Auth\Login;
use App\Models\LoginLog;
use Illuminate\Support\Facades\RateLimiter;

/**
 * 每个测试前清除限速计数器，避免用例间相互污染
 * （array cache driver 在同一进程中持久存在）
 */
beforeEach(function () {
    $key = 'livewire-rate-limiter:'.sha1(Login::class.'|authenticate|127.0.0.1');
    RateLimiter::clear($key);
});

test('enforces rate limiting after 5 failed attempts', function () {
    // 前 5 次失败登录正常处理
    for ($i = 0; $i < 5; $i++) {
        Livewire::test(Login::class)
            ->fillForm(['login' => 'admin', 'password' => 'wrong'])
            ->call('authenticate')
            ->assertHasFormErrors(['login']);
    }

    // 第 6 次被限速：authenticate() 直接 return null，不写入表单错误（不进入认证逻辑）
    Livewire::test(Login::class)
        ->fillForm(['login' => 'admin', 'password' => 'wrong'])
        ->call('authenticate')
        ->assertHasNoFormErrors();

    // 被限速的请求不触发认证逻辑，登录日志仅有 5 条（不含第 6 次）
    expect(LoginLog::where('status', 'failed')->count())->toBe(5);
});

test('logs exactly 5 failed attempts before rate limit kicks in', function () {
    // 前 5 次均会触发 Auth Failed 事件，写入登录日志
    for ($i = 0; $i < 5; $i++) {
        Livewire::test(Login::class)
            ->fillForm(['login' => 'admin', 'password' => 'wrong'])
            ->call('authenticate');
    }

    expect(LoginLog::where('status', 'failed')->count())->toBe(5);

    // 第 6 次被限速，不再触发认证逻辑，日志数量不变
    Livewire::test(Login::class)
        ->fillForm(['login' => 'admin', 'password' => 'wrong'])
        ->call('authenticate');

    expect(LoginLog::where('status', 'failed')->count())->toBe(5);
});

test('rate limit resets after cooldown', function () {
    // 先触发限速
    for ($i = 0; $i < 6; $i++) {
        Livewire::test(Login::class)
            ->fillForm(['login' => 'admin', 'password' => 'wrong'])
            ->call('authenticate');
    }

    // 冷却 61 秒（array cache driver 使用 Carbon，时间旅行有效）
    $this->travel(61)->seconds();

    // 限速重置后，应重新进入认证逻辑并返回表单错误（而非 Filament 通知）
    Livewire::test(Login::class)
        ->fillForm(['login' => 'admin', 'password' => 'wrong'])
        ->call('authenticate')
        ->assertHasFormErrors(['login']);
});
