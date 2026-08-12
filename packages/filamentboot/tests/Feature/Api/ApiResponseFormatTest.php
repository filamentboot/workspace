<?php

namespace Filamentboot\Tests\Feature\Api;

use Filamentboot\Enums\ApiErrorCode;
use Filamentboot\Exceptions\ApiException;
use Filamentboot\FilamentbootServiceProvider;
use Filamentboot\Models\AdminUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\SanctumServiceProvider;
use Orchestra\Testbench\TestCase;
use RuntimeException;
use Spatie\Activitylog\ActivitylogServiceProvider;
use Spatie\LaravelSettings\LaravelSettingsServiceProvider;
use Spatie\Permission\PermissionServiceProvider;

/**
 * API 统一响应格式测试
 *
 * 覆盖 response()->api() / apiError() / apiPaginated() 三个 Macro 与
 * 全局异常渲染（ApiException / 未捕获异常）的标准 JSON 结构。
 *
 * 数据库连接直接沿用根 phpunit.xml 注入的 MySQL 测试库环境变量
 * （本机无 pdo_sqlite 扩展），迁移由 FilamentbootServiceProvider::boot()
 * 的 loadMigrationsFrom 自动注册，无需在测试里重复声明。
 *
 * Testbench 默认 skeleton 没有真实 vendor 目录（未走 workbench 符号链接），
 * Laravel 包自动发现在此环境下失效，因此必须显式注册 Permission /
 * Activitylog / LaravelSettings（本包迁移依赖它们的配置）与 Sanctum
 * （FilamentbootServiceProvider 挂进 api 中间件组最前面的 ResetAuthGuards
 * 会解析 'sanctum' guard，未注册 SanctumServiceProvider 时该 guard 不存在）。
 */
class ApiResponseFormatTest extends TestCase
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

    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware('api')->prefix('api/v1/test')->group(function () {
            Route::get('/success', function () {
                return response()->api(
                    data: ['key' => 'value'],
                    message: '操作成功'
                );
            });

            Route::get('/success-no-data', function () {
                return response()->api(message: '删除成功');
            });

            Route::get('/paginated', function () {
                $items = AdminUser::paginate(10);

                return response()->apiPaginated($items, message: '获取成功');
            });

            Route::get('/error', function () {
                throw new ApiException(
                    errorCode: ApiErrorCode::VALIDATION_FAILED,
                    message: '参数校验失败'
                );
            });

            Route::get('/server-error', function () {
                throw new RuntimeException('未预期的错误');
            });
        });
    }

    /**
     * 成功响应包含标准字段结构
     */
    public function test_success_response_has_standard_field_structure(): void
    {
        $response = $this->getJson('/api/v1/test/success');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data',
            ])
            ->assertJson([
                'success' => true,
                'message' => '操作成功',
                'data'    => ['key' => 'value'],
            ]);
    }

    /**
     * 无数据的成功响应 data 为 null
     */
    public function test_success_response_without_data_has_null_data(): void
    {
        $response = $this->getJson('/api/v1/test/success-no-data');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => '删除成功',
                'data'    => null,
            ]);
    }

    /**
     * ApiException 返回标准错误格式
     */
    public function test_api_exception_returns_standard_error_format(): void
    {
        $response = $this->getJson('/api/v1/test/error');

        $response->assertStatus(422)
            ->assertJsonStructure([
                'success',
                'message',
                'error_code',
                'data',
            ])
            ->assertJson([
                'success'    => false,
                'message'    => '参数校验失败',
                'error_code' => ApiErrorCode::VALIDATION_FAILED->value,
                'data'       => null,
            ]);
    }

    /**
     * 未捕获异常返回 500 标准格式
     */
    public function test_uncaught_exception_returns_500_standard_format(): void
    {
        $response = $this->getJson('/api/v1/test/server-error');

        $response->assertStatus(500)
            ->assertJson([
                'success'    => false,
                'error_code' => ApiErrorCode::SERVER_ERROR->value,
            ]);
    }
}
