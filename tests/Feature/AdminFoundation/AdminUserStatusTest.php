<?php

use FilamentAdmin\Enums\AdminUserStatus;
use FilamentAdmin\Models\AdminUser;

it('禁用管理员不能访问后台面板', function () {
    $user = AdminUser::factory()->create([
        'status' => AdminUserStatus::Disabled,
    ]);

    expect($user->canAccessPanel(filament()->getPanel('admin')))->toBeFalse();
});

it('启用管理员可以访问后台面板', function () {
    $user = AdminUser::factory()->create([
        'status' => AdminUserStatus::Active,
    ]);

    expect($user->canAccessPanel(filament()->getPanel('admin')))->toBeTrue();
});
