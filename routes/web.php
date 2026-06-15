<?php

use App\Http\Controllers\OfficialMarketIndexController;
use Illuminate\Support\Facades\Route;

// FilamentAdmin 演示项目 landing page（/marketing）
// 注意：/ 由 filament-admin-site 插件接管（site.home 路由），演示时访问 /marketing
Route::get('/marketing', function () {
    return view('landing');
})->name('marketing.home');

Route::get('/plugin-market/index.json', OfficialMarketIndexController::class)
    ->name('plugin-market.index');
