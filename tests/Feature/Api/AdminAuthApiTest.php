<?php

use App\Enums\ApiErrorCode;
use Filamentboot\Enums\AdminUserStatus;
use Filamentboot\Models\AdminUser;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;

it('管理员使用账号和密码可以获取 token', function () {
    $admin = AdminUser::factory()->create([
        'account'  => 'testadmin',
        'password' => Hash::make('password123'),
    ]);

    $response = $this->postJson('/api/v1/admin/login', [
        'account'  => 'testadmin',
        'password' => 'password123',
    ]);

    $response->assertStatus(200)
        ->assertJson(['success' => true])
        ->assertJsonPath('data.token_type', 'Bearer')
        ->assertJsonStructure([
            'data' => [
                'access_token',
                'token_type',
                'expires_at',
            ],
        ]);
});

it('管理员使用邮箱和密码可以获取 token', function () {
    $admin = AdminUser::factory()->create([
        'email'    => 'admin@example.com',
        'password' => Hash::make('password123'),
    ]);

    $response = $this->postJson('/api/v1/admin/login', [
        'account'  => 'admin@example.com',
        'password' => 'password123',
    ]);

    $response->assertStatus(200)
        ->assertJson(['success' => true]);
});

it('密码错误时返回 WRONG_CREDENTIALS 错误', function () {
    $admin = AdminUser::factory()->create([
        'account'  => 'testadmin2',
        'password' => Hash::make('correctpassword'),
    ]);

    $response = $this->postJson('/api/v1/admin/login', [
        'account'  => 'testadmin2',
        'password' => 'wrongpassword',
    ]);

    $response->assertStatus(401)
        ->assertJson([
            'success'    => false,
            'error_code' => ApiErrorCode::WRONG_CREDENTIALS->value,
        ]);
});

it('禁用账号登录返回 ACCOUNT_DISABLED 错误', function () {
    $admin = AdminUser::factory()->create([
        'account'  => 'disabledadmin',
        'password' => Hash::make('password123'),
        'status'   => AdminUserStatus::Disabled,
    ]);

    $response = $this->postJson('/api/v1/admin/login', [
        'account'  => 'disabledadmin',
        'password' => 'password123',
    ]);

    $response->assertStatus(403)
        ->assertJson([
            'success'    => false,
            'error_code' => ApiErrorCode::ACCOUNT_DISABLED->value,
        ]);
});

it('登录参数缺失时返回 VALIDATION_FAILED 错误', function () {
    $response = $this->postJson('/api/v1/admin/login', []);

    $response->assertStatus(422)
        ->assertJson([
            'success'    => false,
            'error_code' => ApiErrorCode::VALIDATION_FAILED->value,
        ]);
});

it('携带有效 token 可以访问 me 接口', function () {
    $admin = AdminUser::factory()->create();

    Sanctum::actingAs($admin, [], 'admin');

    $response = $this->getJson('/api/v1/admin/me');

    $response->assertStatus(200)
        ->assertJson(['success' => true])
        ->assertJsonPath('data.id', $admin->id)
        ->assertJsonPath('data.account', $admin->account);
});

it('未认证时访问 me 接口返回 401', function () {
    $response = $this->getJson('/api/v1/admin/me');

    $response->assertStatus(401)
        ->assertJson([
            'success'    => false,
            'error_code' => ApiErrorCode::UNAUTHENTICATED->value,
        ]);
});

it('携带有效 token 可以登出', function () {
    $admin = AdminUser::factory()->create();
    $token = $admin->createToken('api-test', ['*'], now()->addDay())->plainTextToken;

    $response = $this->withToken($token)
        ->deleteJson('/api/v1/admin/logout');

    $response->assertStatus(200)
        ->assertJson([
            'success' => true,
            'data'    => null,
        ]);

    // 登出后 token 应被删除
    $this->withToken($token)
        ->getJson('/api/v1/admin/me')
        ->assertStatus(401);
});
