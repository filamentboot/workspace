<?php

use Filamentboot\Http\Controllers\OfficialMarketIndexController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| 包级 Web 路由
|--------------------------------------------------------------------------
|
| 通过 FilamentbootServiceProvider::boot() 的 loadRoutesFrom 加载，本文件
| 自行声明 middleware('web')，不依赖宿主 bootstrap/app.php 的
| withRouting(web:) 是否存在（loadRoutesFrom 只是单纯 require，不会带
| 任何隐式中间件分组）。
|
*/

Route::middleware('web')->group(function () {
    Route::get('/plugin-market/index.json', OfficialMarketIndexController::class)
        ->name('plugin-market.index');
});
