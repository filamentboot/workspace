<?php

use App\Filament\Pages\Auth\Login;
use App\Models\AdminUser;
use App\Models\LoginLog;
use Illuminate\Support\Facades\Auth;

test('soft deleted user cannot login', function () {
    $user = AdminUser::factory()->create([
        'username' => 'deleted',
        'password' => 'password',
    ]);

    $user->delete(); // 软删除

    Livewire::test(Login::class)
        ->fillForm(['login' => 'deleted', 'password' => 'password'])
        ->call('authenticate')
        ->assertHasFormErrors(['login']);

    $this->assertGuest('admin');
});

test('logs IPv6 addresses correctly', function () {
    $user = AdminUser::factory()->create([
        'username' => 'admin',
        'password' => 'password',
    ]);

    // 设置 IPv6 地址到当前请求
    $this->app['request']->server->set('REMOTE_ADDR', '2001:0db8:85a3:0000:0000:8a2e:0370:7334');

    // 通过 guard 直接认证，触发 Login 事件 → LogAdminLogin
    Auth::guard('admin')->attempt([
        'username' => 'admin',
        'password' => 'password',
    ]);

    $log = LoginLog::where('status', 'success')->latest()->first();
    expect($log->ip_address)->toBe('2001:0db8:85a3:0000:0000:8a2e:0370:7334');
});

test('handles empty user agent', function () {
    $user = AdminUser::factory()->create([
        'username' => 'admin',
        'password' => 'password',
    ]);

    // 清除 User-Agent（LogAdminLogin 读取 request()->userAgent()，即 headers 中的 User-Agent）
    $this->app['request']->headers->remove('User-Agent');

    Auth::guard('admin')->attempt([
        'username' => 'admin',
        'password' => 'password',
    ]);

    $log = LoginLog::where('status', 'success')->latest()->first();
    expect($log->user_agent)->toBeNull();
});

test('username is case insensitive on MySQL utf8mb4_unicode_ci', function () {
    AdminUser::factory()->create([
        'username' => 'Admin',
        'password' => 'password',
    ]);

    // MySQL utf8mb4_unicode_ci 排序规则下，username 查询不区分大小写
    // 'admin' 可匹配数据库中的 'Admin'
    Livewire::test(Login::class)
        ->fillForm(['login' => 'admin', 'password' => 'password'])
        ->call('authenticate')
        ->assertHasNoFormErrors();

    $this->assertAuthenticated('admin');
});

test('email is case insensitive on MySQL', function () {
    AdminUser::factory()->create([
        'email'    => 'Admin@Example.Com',
        'password' => 'password',
    ]);

    // MySQL utf8mb4_unicode_ci 排序规则下，email 查询不区分大小写
    Livewire::test(Login::class)
        ->fillForm(['login' => 'admin@example.com', 'password' => 'password'])
        ->call('authenticate')
        ->assertHasNoFormErrors();

    $this->assertAuthenticated('admin');
});

test('empty login field shows validation error', function () {
    Livewire::test(Login::class)
        ->fillForm(['login' => '', 'password' => 'password'])
        ->call('authenticate')
        ->assertHasFormErrors(['login']);
});

test('empty password field shows validation error', function () {
    Livewire::test(Login::class)
        ->fillForm(['login' => 'admin', 'password' => ''])
        ->call('authenticate')
        ->assertHasFormErrors(['password']);
});
