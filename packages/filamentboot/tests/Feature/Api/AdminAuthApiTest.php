<?php

namespace Filamentboot\Tests\Feature\Api;

use Filamentboot\Enums\AdminUserStatus;
use Filamentboot\Enums\ApiErrorCode;
use Filamentboot\FilamentbootServiceProvider;
use Filamentboot\Models\AdminUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\SanctumServiceProvider;
use Orchestra\Testbench\TestCase;
use Spatie\Activitylog\ActivitylogServiceProvider;
use Spatie\LaravelSettings\LaravelSettingsServiceProvider;
use Spatie\Permission\PermissionServiceProvider;

/**
 * 管理员 API 认证测试
 *
 * 覆盖 /api/v1/admin/{login,me,logout} 三个端点：
 * 账号/邮箱登录取 token、密码错误、账号禁用、参数缺失、Bearer token 鉴权、登出后 token 失效。
 *
 * 数据库连接直接沿用根 phpunit.xml 注入的 MySQL 测试库环境变量
 * （本机无 pdo_sqlite 扩展），迁移由 FilamentbootServiceProvider::boot()
 * 的 loadMigrationsFrom 自动注册，无需在测试里重复声明。
 *
 * Testbench 默认 skeleton 没有真实 vendor 目录（未走 workbench 符号链接），
 * Laravel 包自动发现在此环境下失效，因此必须显式注册 Permission /
 * Activitylog / LaravelSettings（本包迁移依赖它们的配置）与 Sanctum
 * （auth:sanctum 中间件解析的 guard 由其 ServiceProvider 注册）。
 */
class AdminAuthApiTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return list<class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [
            FilamentbootServiceProvider::class,
            PermissionServiceProvider::class,
            ActivitylogServiceProvider::class,
            LaravelSettingsServiceProvider::class,
            SanctumServiceProvider::class,
        ];
    }

    /**
     * 管理员使用账号和密码可以获取 token
     */
    public function test_admin_can_get_token_using_account_and_password(): void
    {
        AdminUser::factory()->create([
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
    }

    /**
     * 管理员使用邮箱和密码可以获取 token
     */
    public function test_admin_can_get_token_using_email_and_password(): void
    {
        AdminUser::factory()->create([
            'email'    => 'admin@example.com',
            'password' => Hash::make('password123'),
        ]);

        $response = $this->postJson('/api/v1/admin/login', [
            'account'  => 'admin@example.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(200)
            ->assertJson(['success' => true]);
    }

    /**
     * 密码错误时返回 WRONG_CREDENTIALS 错误
     */
    public function test_login_with_wrong_password_returns_wrong_credentials_error(): void
    {
        AdminUser::factory()->create([
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
    }

    /**
     * 禁用账号登录返回 ACCOUNT_DISABLED 错误
     */
    public function test_login_with_disabled_account_returns_account_disabled_error(): void
    {
        AdminUser::factory()->create([
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
    }

    /**
     * 登录参数缺失时返回 VALIDATION_FAILED 错误
     */
    public function test_login_with_missing_params_returns_validation_failed_error(): void
    {
        $response = $this->postJson('/api/v1/admin/login', []);

        $response->assertStatus(422)
            ->assertJson([
                'success'    => false,
                'error_code' => ApiErrorCode::VALIDATION_FAILED->value,
            ]);
    }

    /**
     * 携带有效 token 可以访问 me 接口
     */
    public function test_me_endpoint_accessible_with_valid_token(): void
    {
        $admin = AdminUser::factory()->create();
        $token = $admin->createToken('api-test', ['*'], now()->addDay())->plainTextToken;

        $response = $this->withToken($token)->getJson('/api/v1/admin/me');

        $response->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonPath('data.id', $admin->id)
            ->assertJsonPath('data.account', $admin->account);
    }

    /**
     * 未认证时访问 me 接口返回 401
     */
    public function test_me_endpoint_returns_401_when_unauthenticated(): void
    {
        $response = $this->getJson('/api/v1/admin/me');

        $response->assertStatus(401)
            ->assertJson([
                'success'    => false,
                'error_code' => ApiErrorCode::UNAUTHENTICATED->value,
            ]);
    }

    /**
     * 携带有效 token 可以登出
     */
    public function test_admin_can_logout_with_valid_token(): void
    {
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
    }
}
