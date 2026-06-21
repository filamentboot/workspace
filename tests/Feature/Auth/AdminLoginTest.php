<?php

use Filamentboot\Filament\Pages\Auth\Login;
use Filamentboot\Models\AdminUser;

/**
 * 管理员登录功能测试（使用 Livewire 测试方式）
 */
test('login page is accessible', function () {
    $this->get('/admin/login')->assertOk();
});

test('allows login with username', function () {
    $user = AdminUser::factory()->create([
        'account'  => 'admin',
        'email'    => 'admin@example.com',
        'password' => 'password',
    ]);

    Livewire::test(Login::class)
        ->fillForm([
            'login'    => 'admin',
            'password' => 'password',
        ])
        ->call('authenticate')
        ->assertHasNoFormErrors();

    $this->assertAuthenticatedAs($user, 'admin');
});

test('allows login with email', function () {
    $user = AdminUser::factory()->create([
        'account'  => 'admin',
        'email'    => 'admin@example.com',
        'password' => 'password',
    ]);

    Livewire::test(Login::class)
        ->fillForm([
            'login'    => 'admin@example.com',
            'password' => 'password',
        ])
        ->call('authenticate')
        ->assertHasNoFormErrors();

    $this->assertAuthenticatedAs($user, 'admin');
});

test('fails login with invalid password', function () {
    AdminUser::factory()->create([
        'account'  => 'admin',
        'password' => 'password',
    ]);

    Livewire::test(Login::class)
        ->fillForm([
            'login'    => 'admin',
            'password' => 'wrong-password',
        ])
        ->call('authenticate')
        ->assertHasFormErrors(['login']);

    $this->assertGuest('admin');
});

test('prevents username enumeration attack', function () {
    AdminUser::factory()->create([
        'account'  => 'existing',
        'password' => 'password',
    ]);

    // 不存在的用户，错误密码 —— 应返回通用错误
    Livewire::test(Login::class)
        ->fillForm(['login' => 'nonexistent', 'password' => 'wrong'])
        ->call('authenticate')
        ->assertHasFormErrors(['login' => '用户名/邮箱或密码错误']);

    // 存在的用户，错误密码 —— 应返回相同的通用错误
    Livewire::test(Login::class)
        ->fillForm(['login' => 'existing', 'password' => 'wrong'])
        ->call('authenticate')
        ->assertHasFormErrors(['login' => '用户名/邮箱或密码错误']);
});

test('logs successful login to database', function () {
    $user = AdminUser::factory()->create([
        'account'  => 'admin',
        'password' => 'password',
    ]);

    Livewire::test(Login::class)
        ->fillForm(['login' => 'admin', 'password' => 'password'])
        ->call('authenticate');

    $this->assertDatabaseHas('login_logs', [
        'admin_user_id' => $user->id,
        'username'      => 'admin',
        'status'        => 'success',
    ]);
});

test('logs failed login to database', function () {
    Livewire::test(Login::class)
        ->fillForm(['login' => 'nonexistent', 'password' => 'wrong'])
        ->call('authenticate');

    $this->assertDatabaseHas('login_logs', [
        'admin_user_id'  => null,
        'status'         => 'failed',
        'failure_reason' => 'invalid_credentials',
    ]);
});
