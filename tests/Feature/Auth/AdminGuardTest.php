<?php

use Filamentboot\Models\AdminUser;

/**
 * Admin Guard 配置测试
 */
test('admin guard uses correct provider', function () {
    $config = config('auth.guards.admin');

    expect($config['driver'])->toBe('session')
        ->and($config['provider'])->toBe('admin_users');
});

test('admin users provider uses AdminUser model', function () {
    $config = config('auth.providers.admin_users');

    expect($config['driver'])->toBe('eloquent')
        ->and($config['model'])->toBe(AdminUser::class);
});

test('admin guard can authenticate admin user', function () {
    $user = AdminUser::factory()->create([
        'account'  => 'testadmin',
        'password' => 'password',
    ]);

    $authenticated = Auth::guard('admin')->attempt([
        'account'  => 'testadmin',
        'password' => 'password',
    ]);

    expect($authenticated)->toBeTrue()
        ->and(Auth::guard('admin')->user()->id)->toBe($user->id);
});
