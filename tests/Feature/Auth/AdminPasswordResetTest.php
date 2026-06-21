<?php

use Filamentboot\Models\AdminUser;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;

/**
 * 管理员密码重置功能测试（admin_users broker 闭环）
 */
it('发送密码重置链接：通过 admin_users broker 发送通知到指定邮箱', function () {
    Notification::fake();

    $user = AdminUser::factory()->create([
        'email' => 'admin@example.com',
    ]);

    // 通过 admin_users broker 发送重置链接
    $status = Password::broker('admin_users')->sendResetLink(['email' => 'admin@example.com']);

    // 断言返回 RESET_LINK_SENT
    expect($status)->toBe(Password::RESET_LINK_SENT);

    // 断言向该用户发送了密码重置通知
    Notification::assertSentTo($user, ResetPassword::class);
});

it('密码重置完成后可用新密码通过 admin guard 认证', function () {
    $user = AdminUser::factory()->create([
        'email'    => 'admin@example.com',
        'password' => Hash::make('old-password'),
    ]);

    // 通过 admin_users broker 生成 token 并重置密码
    $token    = null;
    Password::broker('admin_users')->sendResetLink(
        ['email' => 'admin@example.com'],
        function ($user, $generatedToken) use (&$token) {
            $token = $generatedToken;
        }
    );

    expect($token)->not->toBeNull('应生成密码重置 token');

    // 使用 token 重置密码
    $status = Password::broker('admin_users')->reset(
        [
            'email'                 => 'admin@example.com',
            'password'              => 'new-password-123',
            'password_confirmation' => 'new-password-123',
            'token'                 => $token,
        ],
        function ($user, $password) {
            $user->forceFill(['password' => Hash::make($password)])->save();
        }
    );

    // 断言重置成功
    expect($status)->toBe(Password::PASSWORD_RESET);

    // 断言新密码可通过 admin guard 认证
    $authenticated = Auth::guard('admin')->attempt([
        'email'    => 'admin@example.com',
        'password' => 'new-password-123',
    ]);

    expect($authenticated)->toBeTrue('新密码应可通过 admin guard 认证');
});
