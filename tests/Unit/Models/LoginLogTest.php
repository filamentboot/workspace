<?php

use FilamentAdmin\Models\AdminUser;
use FilamentAdmin\Models\LoginLog;

test('login log can be created', function () {
    $user = AdminUser::factory()->create();

    $log = LoginLog::factory()->create([
        'admin_user_id' => $user->id,
        'status'        => 'success',
    ]);

    expect($log->status)->toBe('success')
        ->and($log->admin_user_id)->toBe($user->id)
        ->and($log->exists)->toBeTrue();
});

test('login log belongs to admin user', function () {
    $user = AdminUser::factory()->create();
    $log  = LoginLog::factory()->create(['admin_user_id' => $user->id]);

    expect($log->adminUser->id)->toBe($user->id);
});

test('login log can have null admin_user_id for failed attempts', function () {
    $log = LoginLog::factory()->create([
        'admin_user_id' => null,
        'username'      => 'nonexistent',
        'status'        => 'failed',
    ]);

    expect($log->admin_user_id)->toBeNull()
        ->and($log->username)->toBe('nonexistent')
        ->and($log->adminUser)->toBeNull();
});

test('login log records ip address and user agent', function () {
    $log = LoginLog::factory()->create([
        'ip_address' => '192.168.1.1',
        'user_agent' => 'Mozilla/5.0',
    ]);

    expect($log->ip_address)->toBe('192.168.1.1')
        ->and($log->user_agent)->toBe('Mozilla/5.0');
});
