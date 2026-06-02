<?php

use App\Listeners\LogAdminLogin;
use App\Models\AdminUser;
use App\Models\LoginLog;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;

test('logs successful login event', function () {
    $user = AdminUser::factory()->create(['account' => 'admin']);

    $event = new Login('admin', $user, false);

    $listener = new LogAdminLogin;
    $listener->handle($event);

    expect(LoginLog::count())->toBe(1);

    $log = LoginLog::first();
    expect($log->status)->toBe('success')
        ->and($log->admin_user_id)->toBe($user->id)
        ->and($log->username)->toBe('admin');
});

test('logs failed login event with username', function () {
    $event = new Failed('admin', null, ['account' => 'nonexistent', 'password' => 'wrong']);

    $listener = new LogAdminLogin;
    $listener->handle($event);

    expect(LoginLog::count())->toBe(1);

    $log = LoginLog::first();
    expect($log->status)->toBe('failed')
        ->and($log->admin_user_id)->toBeNull()
        ->and($log->username)->toBe('nonexistent')
        ->and($log->failure_reason)->toBe('invalid_credentials');
});

test('logs failed login event with email', function () {
    $event = new Failed('admin', null, ['email' => 'test@example.com', 'password' => 'wrong']);

    $listener = new LogAdminLogin;
    $listener->handle($event);

    $log = LoginLog::first();
    expect($log->username)->toBe('test@example.com');
});

test('logs failed login event with login field', function () {
    $event = new Failed('admin', null, ['login' => 'admin', 'password' => 'wrong']);

    $listener = new LogAdminLogin;
    $listener->handle($event);

    $log = LoginLog::first();
    expect($log->username)->toBe('admin');
});

test('ignores non-admin guard events', function () {
    $event = new Login('web', AdminUser::factory()->make(), false);

    $listener = new LogAdminLogin;
    $listener->handle($event);

    expect(LoginLog::count())->toBe(0);
});

test('records ip address and user agent', function () {
    $user = AdminUser::factory()->create();

    $this->app['request']->server->set('REMOTE_ADDR', '192.168.1.100');
    $this->app['request']->headers->set('User-Agent', 'Test Browser');

    $event = new Login('admin', $user, false);

    $listener = new LogAdminLogin;
    $listener->handle($event);

    $log = LoginLog::first();
    expect($log->ip_address)->toBe('192.168.1.100')
        ->and($log->user_agent)->toBe('Test Browser');
});
