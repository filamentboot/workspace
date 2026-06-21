<?php

use App\Enums\ApiErrorCode;
use App\Exceptions\ApiException;
use Filamentboot\Models\AdminUser;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
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
});

it('成功响应包含标准字段结构', function () {
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
});

it('无数据的成功响应 data 为 null', function () {
    $response = $this->getJson('/api/v1/test/success-no-data');

    $response->assertStatus(200)
        ->assertJson([
            'success' => true,
            'message' => '删除成功',
            'data'    => null,
        ]);
});

it('ApiException 返回标准错误格式', function () {
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
});

it('未捕获异常返回 500 标准格式', function () {
    $response = $this->getJson('/api/v1/test/server-error');

    $response->assertStatus(500)
        ->assertJson([
            'success'    => false,
            'error_code' => ApiErrorCode::SERVER_ERROR->value,
        ]);
});
