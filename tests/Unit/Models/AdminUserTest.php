<?php

use App\Models\AdminUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * AdminUser 模型单元测试
 */
test('admin user can be created', function () {
    $user = AdminUser::factory()->create([
        'username' => 'testadmin',
        'email'    => 'admin@example.com',
    ]);

    expect($user->username)->toBe('testadmin')
        ->and($user->email)->toBe('admin@example.com')
        ->and($user->exists)->toBeTrue();
});

test('admin user has login logs relationship', function () {
    $user = AdminUser::factory()->create();

    expect($user->loginLogs())->toBeInstanceOf(HasMany::class);
});

test('admin user hides sensitive attributes', function () {
    $user  = AdminUser::factory()->create();
    $array = $user->toArray();

    expect($array)->not->toHaveKey('password')
        ->and($array)->not->toHaveKey('two_factor_secret')
        ->and($array)->not->toHaveKey('two_factor_recovery_codes')
        ->and($array)->not->toHaveKey('remember_token');
});

test('admin user password is hashed', function () {
    $user = AdminUser::factory()->create([
        'password' => 'plaintext',
    ]);

    expect($user->password)->not->toBe('plaintext')
        ->and(Hash::check('plaintext', $user->password))->toBeTrue();
});

test('admin user can access panel', function () {
    $user = AdminUser::factory()->create();

    expect($user->canAccessPanel(mock(Panel::class)))->toBeTrue();
});
