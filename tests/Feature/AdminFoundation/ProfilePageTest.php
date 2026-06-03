<?php

use FilamentAdmin\Filament\Pages\Profile;
use FilamentAdmin\Models\AdminUser;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

it('已登录管理员可以访问个人资料页', function () {
    $admin = AdminUser::factory()->create();

    $this->actingAs($admin, 'admin')
        ->get(filament()->getProfileUrl())
        ->assertSuccessful();
});

it('个人资料表单包含 account/nickname/email/mobile 字段', function () {
    $admin = AdminUser::factory()->create();

    Livewire::actingAs($admin, 'admin')
        ->test(Profile::class)
        ->assertFormFieldExists('account')
        ->assertFormFieldExists('nickname')
        ->assertFormFieldExists('email')
        ->assertFormFieldExists('mobile');
});

it('管理员可以更新账号、昵称、手机号', function () {
    $admin = AdminUser::factory()->create([
        'account'  => 'old_account',
        'nickname' => '旧昵称',
        'mobile'   => null,
    ]);

    Livewire::actingAs($admin, 'admin')
        ->test(Profile::class)
        ->fillForm([
            'account'  => 'new_account',
            'nickname' => '新昵称',
            'email'    => $admin->email,
            'mobile'   => '13800138000',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $admin->refresh();
    expect($admin->account)->toBe('new_account')
        ->and($admin->nickname)->toBe('新昵称')
        ->and($admin->mobile)->toBe('13800138000');
});

it('管理员可以通过个人资料页修改密码', function () {
    $admin = AdminUser::factory()->create([
        'password' => Hash::make('old_password'),
    ]);

    Livewire::actingAs($admin, 'admin')
        ->test(Profile::class)
        ->fillForm([
            'account'              => $admin->account,
            'nickname'             => $admin->nickname,
            'email'                => $admin->email,
            'password'             => 'New_password_123',
            'passwordConfirmation' => 'New_password_123',
            'currentPassword'      => 'old_password',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $admin->refresh();
    expect(Hash::check('New_password_123', $admin->password))->toBeTrue();
});

it('account 字段唯一性验证：不能使用其他管理员的账号', function () {
    $other  = AdminUser::factory()->create(['account' => 'taken_account']);
    $admin  = AdminUser::factory()->create(['account' => 'my_account']);

    Livewire::actingAs($admin, 'admin')
        ->test(Profile::class)
        ->fillForm([
            'account'  => 'taken_account',
            'nickname' => $admin->nickname,
            'email'    => $admin->email,
        ])
        ->call('save')
        ->assertHasFormErrors(['account']);
});
