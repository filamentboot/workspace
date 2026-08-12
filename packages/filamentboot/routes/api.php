<?php

use Filamentboot\Http\Controllers\Api\V1\Admin\AuthController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| 包级 API 路由
|--------------------------------------------------------------------------
|
| 通过 FilamentbootServiceProvider::boot() 的 loadRoutesFrom 加载，本文件
| 自行声明 prefix('api')->middleware('api')，不依赖宿主 bootstrap/app.php
| 的 withRouting(api:) 是否存在（loadRoutesFrom 只是单纯 require，不会带
| 任何隐式中间件分组）。
|
| 版本策略：/api/v1/ 前缀
| 认证方式：Laravel Sanctum Bearer Token
|
*/

Route::prefix('api/v1')->middleware('api')->group(function () {

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
