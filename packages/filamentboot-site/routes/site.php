<?php

use Illuminate\Support\Facades\Route;
use Filamentboot\FilamentbootSite\Http\Controllers\SiteFrontController;
use Filamentboot\FilamentbootSite\Http\Middleware\SetLocaleMiddleware;

/*
|--------------------------------------------------------------------------
| 官网前台路由（site.php）
|--------------------------------------------------------------------------
| 仅在 plugins.is_enabled = true 时由 SiteServiceProvider::registerFrontend()
| 通过 loadRoutesFrom 加载，插件禁用时此文件不被引入（T-10-04-01，Pitfall 1）。
|
| 双语路由（D-10-06/07）：
|   - 中文路由：/、/cases、/cases/{slug}、/solutions、...、/{slug}（静态页）
|   - 英文路由：/en/、/en/cases、...（SetLocaleMiddleware 切换 locale 为 'en'）
|
| Pitfall 4 防护：/{slug} 使用负向预查正则 ^(?!en$)，排除与 /en 路由冲突。
| /{slug} 必须放在所有固定前缀路由之后（防贪婪匹配）。
*/

// -----------------------------------------------------------------------
// 中文路由（默认，无前缀）
// -----------------------------------------------------------------------
Route::middleware('web')
    ->controller(SiteFrontController::class)
    ->group(function () {
        // 首页
        Route::get('/', 'home')->name('site.home');

        // 装修案例
        Route::get('/cases', 'caseIndex')->name('site.cases.index');
        Route::get('/cases/{slug}', 'caseShow')->name('site.cases.show');

        // 智能方案
        Route::get('/solutions', 'solutionIndex')->name('site.solutions.index');
        Route::get('/solutions/{slug}', 'solutionShow')->name('site.solutions.show');

        // 智能产品
        Route::get('/products', 'productIndex')->name('site.products.index');
        Route::get('/products/{slug}', 'productShow')->name('site.products.show');

        // 静态页面：/{slug} 用负向预查排除 'en'（Pitfall 4，T-10-04-03）
        // 必须放在所有固定前缀路由之后，防止贪婪匹配覆盖上方路由
        Route::get('/{slug}', 'page')
            ->name('site.page')
            ->where('slug', '^(?!en$)[a-z0-9\-]+$');
    });

// -----------------------------------------------------------------------
// 英文路由（/en/ 前缀，SetLocaleMiddleware 切换 locale，D-10-07/09）
// -----------------------------------------------------------------------
Route::middleware(['web', SetLocaleMiddleware::class])
    ->prefix('en')
    ->controller(SiteFrontController::class)
    ->group(function () {
        // 英文首页
        Route::get('/', 'home')->name('site.en.home');

        // 英文装修案例
        Route::get('/cases', 'caseIndex')->name('site.en.cases.index');
        Route::get('/cases/{slug}', 'caseShow')->name('site.en.cases.show');

        // 英文智能方案
        Route::get('/solutions', 'solutionIndex')->name('site.en.solutions.index');
        Route::get('/solutions/{slug}', 'solutionShow')->name('site.en.solutions.show');

        // 英文智能产品
        Route::get('/products', 'productIndex')->name('site.en.products.index');
        Route::get('/products/{slug}', 'productShow')->name('site.en.products.show');

        // 英文静态页面
        Route::get('/{slug}', 'page')
            ->name('site.en.page')
            ->where('slug', '^[a-z0-9\-]+$');
    });
