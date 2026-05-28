<?php

use App\Filament\Pages\Auth\Login;
use App\Models\AdminUser;
use App\Models\LoginLog;
use Stephenjude\FilamentTwoFactorAuthentication\Actions\DisableTwoFactorAuthentication;

test('complete authentication flow without 2FA', function () {
    // 1. 创建未启用 2FA 的用户
    $user = AdminUser::factory()->create([
        'username' => 'testuser',
        'email'    => 'test@example.com',
        'password' => 'password123',
    ]);

    // 2. 未认证时访问受保护页面，应重定向到登录页
    $this->get('/admin')->assertRedirect('/admin/login');

    // 3. 登录页可访问
    $this->get('/admin/login')->assertOk();

    // 4. 通过 Livewire 表单以 username 登录
    Livewire::test(Login::class)
        ->fillForm(['login' => 'testuser', 'password' => 'password123'])
        ->call('authenticate')
        ->assertHasNoFormErrors();

    $this->assertAuthenticatedAs($user, 'admin');

    // 5. 验证登录日志
    $log = LoginLog::where('status', 'success')->latest()->first();
    expect($log->status)->toBe('success')
        ->and($log->admin_user_id)->toBe($user->id)
        ->and($log->username)->toBe('testuser');

    // 6. 认证后访问面板，应可正常访问（2FA challenge 已通过因为 2FA 未启用）
    $this->actingAs($user, 'admin')->get('/admin')->assertOk();
});

test('complete authentication flow with email login', function () {
    $user = AdminUser::factory()->create([
        'username' => 'testuser',
        'email'    => 'test@example.com',
        'password' => 'password123',
    ]);

    // 使用 email 登录
    Livewire::test(Login::class)
        ->fillForm(['login' => 'test@example.com', 'password' => 'password123'])
        ->call('authenticate')
        ->assertHasNoFormErrors();

    $this->assertAuthenticatedAs($user, 'admin');

    // 登录日志应优先记录 username（成功登录后可从 $event->user 获取）
    $log = LoginLog::where('status', 'success')->latest()->first();
    expect($log->username)->toBe('testuser');
});

test('failed login attempts are logged correctly', function () {
    // 3 次失败尝试（不存在的用户）
    for ($i = 0; $i < 3; $i++) {
        Livewire::test(Login::class)
            ->fillForm(['login' => 'nonexistent', 'password' => 'wrong'])
            ->call('authenticate');
    }

    expect(LoginLog::where('status', 'failed')->count())->toBe(3);

    $logs = LoginLog::where('status', 'failed')->get();
    foreach ($logs as $log) {
        expect($log->username)->toBe('nonexistent')
            ->and($log->failure_reason)->toBe('invalid_credentials');
    }
});

test('complete authentication flow with 2FA enabled', function () {
    $user = AdminUser::factory()->withTwoFactor()->create([
        'username' => 'admin2fa',
        'password' => 'password',
    ]);

    // 登录成功
    Livewire::test(Login::class)
        ->fillForm(['login' => 'admin2fa', 'password' => 'password'])
        ->call('authenticate')
        ->assertHasNoFormErrors();

    $this->assertAuthenticatedAs($user, 'admin');

    // 但尚未通过 2FA challenge，访问面板应重定向到 challenge 页
    $this->actingAs($user, 'admin')
        ->get('/admin')
        ->assertRedirect('/admin/two-factor-challenge');

    // 禁用 2FA 后可正常访问面板
    app(DisableTwoFactorAuthentication::class)($user);
    $this->actingAs($user->fresh(), 'admin')
        ->get('/admin')
        ->assertOk();
});
