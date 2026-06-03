<?php

use App\Http\Controllers\Api\V1\Admin\AuthController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API 路由
|--------------------------------------------------------------------------
|
| 版本策略：/api/v1/ 前缀
| 认证方式：Laravel Sanctum Bearer Token
|
*/

Route::prefix('v1')->group(function () {

    /*
    |----------------------------------------------------------------------
    | 管理员 API - 公开接口（无需认证）
    |----------------------------------------------------------------------
    */
    Route::prefix('admin')->name('api.v1.admin.')->group(function () {
        Route::post('login', [AuthController::class, 'login'])->name('login');
    });

    /*
    |----------------------------------------------------------------------
    | 管理员 API - 需要认证的接口
    |----------------------------------------------------------------------
    */
    Route::prefix('admin')
        ->name('api.v1.admin.')
        ->middleware(['auth:sanctum'])
        ->group(function () {
            Route::get('me', [AuthController::class, 'me'])->name('me');
            Route::delete('logout', [AuthController::class, 'logout'])->name('logout');
        });
});
