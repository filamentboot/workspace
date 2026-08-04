<?php

use Filamentboot\FilamentbootSite\Http\Controllers\SiteFrontController;
use Filamentboot\FilamentbootSite\Http\Controllers\SitemapController;
use Filamentboot\FilamentbootSite\Http\Middleware\CaptureVisitorAttribution;
use Illuminate\Routing\RouteRegistrar;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| 官网前台路由（site.php）
|--------------------------------------------------------------------------
| 仅在 plugins.is_enabled = true 时由 SiteServiceProvider::registerFrontend()
| 通过 loadRoutesFrom 加载，插件禁用时此文件不被引入（T-10-04-01，Pitfall 1）。
|
| 挂载模式由 config('filamentboot-site.route.mode') 决定：
|   prefix（默认）— 挂在 /{prefix} 下，不抢占宿主根路由
|   root          — 接管根路径，项目本身就是官网时使用
|   domain        — 绑定独立域名/子域名
|
| CMS v1 为中文单语言，不再注册 /en 镜像路由。
|
| /{slug} 必须放在所有固定前缀路由之后（防贪婪匹配），并用负向预查排除
| config 中声明的保留 slug，避免 root 模式下吞掉 /admin、/sitemap.xml 等固定路径。
*/

/** @var array<string, mixed> $routeConfig */
$routeConfig = config('filamentboot-site.route', []);

$mode   = $routeConfig['mode'] ?? 'prefix';
$domain = $routeConfig['domain'] ?? null;

/** @var list<string> $reservedSlugs */
$reservedSlugs = $routeConfig['reserved_slugs'] ?? [];

// 保留 slug 负向预查，防止动态页面路由吞掉固定系统路径
$escaped     = array_map(static fn (string $slug): string => preg_quote($slug, '/'), $reservedSlugs);
$slugPattern = $escaped === []
    ? '^[a-z0-9\-]+$'
    : '^(?!(?:'.implode('|', $escaped).')$)[a-z0-9\-]+$';

/**
 * 按挂载模式套用 domain/prefix 约束
 *
 * domain 模式未配置域名时降级为 prefix，避免注册出无主机名的路由。
 */
$applyMode = static function (RouteRegistrar $registrar) use ($mode, $domain, $routeConfig) {
    if ($mode === 'domain' && is_string($domain) && $domain !== '') {
        return $registrar->domain($domain);
    }

    if ($mode === 'root') {
        return $registrar;
    }

    /** @var string $prefix */
    $prefix = $routeConfig['prefix'] ?? 'site';

    return $registrar->prefix($prefix);
};

// 固定系统路径：必须先于动态 /{slug} 注册，且已列入 reserved_slugs
$applyMode(Route::middleware('web')->controller(SitemapController::class))
    ->group(function (): void {
        Route::get('/sitemap.xml', 'sitemap')->name('site.sitemap');
        Route::get('/robots.txt', 'robots')->name('site.robots');
    });

// 内容页额外挂首触归因中间件：sitemap/robots 不需要，也不应为爬虫开 session
$applyMode(Route::middleware(['web', CaptureVisitorAttribution::class])->controller(SiteFrontController::class))
    ->group(function () use ($slugPattern): void {
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

        // 资讯：归档先于详情注册。两者段数不同本不会冲突，但保持「越具体越靠前」，
        // 日后若把归档改成 /news/{year}/{month} 也不必回头调顺序。
        Route::get('/news', 'newsIndex')->name('site.news.index');
        Route::get('/news/archive/{year}/{month}', 'newsArchive')
            ->name('site.news.archive')
            ->where(['year' => '[0-9]{4}', 'month' => '0[1-9]|1[0-2]']);
        Route::get('/news/{slug}', 'newsShow')->name('site.news.show');

        // 草稿预览（#16）：必须先于 /{slug} 注册。preview 已在 reserved_slugs 里，
        // 所以 /{slug} 不会吞掉它，但顺序仍按「越具体越靠前」保持。
        // 授权在控制器里做（签名 或 已登录管理员），不挂 signed 中间件——
        // 那会把已登录管理员挡在门外。
        Route::get('/preview/{page}', 'preview')
            ->name('site.page.preview')
            ->where('page', '[0-9]+');

        // 静态页面（必须最后注册，slug 已排除保留路径，T-10-04-03 参数绑定防注入）
        Route::get('/{slug}', 'page')
            ->name('site.page')
            ->where('slug', $slugPattern);
    });

unset($applyMode, $routeConfig, $mode, $domain, $reservedSlugs, $escaped, $slugPattern);
