<?php

use FilamentAdmin\Models\AdminUser;
use Stephenjude\FilamentTwoFactorAuthentication\Actions\DisableTwoFactorAuthentication;

test('admin user can enable 2FA', function () {
    $user = AdminUser::factory()->create();

    // 初始状态：2FA 未启用
    expect($user->hasEnabledTwoFactorAuthentication())->toBeFalse()
        ->and($user->two_factor_confirmed_at)->toBeNull();

    // 直接写入 2FA 字段启用
    $user->forceFill([
        'two_factor_secret'         => encrypt('test-secret'),
        'two_factor_recovery_codes' => encrypt(json_encode(['code1', 'code2'])),
        'two_factor_confirmed_at'   => now(),
    ])->save();

    expect($user->fresh()->hasEnabledTwoFactorAuthentication())->toBeTrue();
});

test('admin user factory can create user with 2FA enabled', function () {
    $user = AdminUser::factory()->withTwoFactor()->create();

    expect($user->hasEnabledTwoFactorAuthentication())->toBeTrue()
        ->and($user->two_factor_secret)->not->toBeNull()
        ->and($user->two_factor_recovery_codes)->not->toBeNull()
        ->and($user->two_factor_confirmed_at)->not->toBeNull();
});

test('admin user has 2FA trait methods available', function () {
    $user = AdminUser::factory()->create();

    expect(method_exists($user, 'hasEnabledTwoFactorAuthentication'))->toBeTrue()
        ->and(method_exists($user, 'recoveryCodes'))->toBeTrue()
        ->and(method_exists($user, 'twoFactorQrCodeSvg'))->toBeTrue();
});

test('user with 2FA enabled is redirected to challenge page when accessing admin', function () {
    $user = AdminUser::factory()->withTwoFactor()->create();

    // 已认证但未通过 2FA challenge，访问 /admin 应重定向到验证页
    $this->actingAs($user, 'admin')
        ->get('/admin')
        ->assertRedirect('/admin/two-factor-challenge');
});

test('can disable 2FA using DisableTwoFactorAuthentication action', function () {
    $user = AdminUser::factory()->withTwoFactor()->create();

    expect($user->hasEnabledTwoFactorAuthentication())->toBeTrue();

    // 通过 Action 禁用 2FA，清空所有相关字段
    app(DisableTwoFactorAuthentication::class)($user);

    expect($user->fresh()->two_factor_confirmed_at)->toBeNull()
        ->and($user->fresh()->two_factor_secret)->toBeNull()
        ->and($user->fresh()->two_factor_recovery_codes)->toBeNull()
        ->and($user->fresh()->hasEnabledTwoFactorAuthentication())->toBeFalse();
});
