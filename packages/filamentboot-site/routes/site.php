<?php

use Filamentboot\FilamentbootSite\Cms\Routing\SiteCacheHeaders;
use Filamentboot\FilamentbootSite\Http\Controllers\ContactSubmissionController;
use Filamentboot\FilamentbootSite\Http\Controllers\GatedDownloadController;
use Filamentboot\FilamentbootSite\Http\Controllers\SiteFrontController;
use Filamentboot\FilamentbootSite\Http\Controllers\SitemapController;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Routing\Middleware\ValidateSignature;
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
|
| ⚠️ 中间件分三档（#29）：
|
|   PUBLIC_STACK  内容页与 sitemap/robots。**不含 StartSession / CSRF**——起了 session
|                 就有 Set-Cookie，共享缓存直接失效。SiteCacheHeaders 负责打
|                 `public, max-age`，且只在响应确实没有 Cookie 时才打。
|   web           只有 /preview/{type}/{id} 用。它靠 auth('admin') 判权，没 session 就永远 403。
|   无状态 POST    询盘提交端点。没有 session 就发不出 CSRF token，所以那条路由不挂 web，
|                 防刷靠 throttle + 蜜罐 + 客户端耗时 + IP 限流（见 Services\ContactSubmission）。
*/

/**
 * 公开页中间件栈
 *
 * 刻意不含 web 组：EncryptCookies / StartSession / CSRF / ShareErrorsFromSession
 * 一个都不要。SubstituteBindings 留着——本文件的内容路由都用字符串 slug 不需要它，
 * 但宿主若在同一组里追加带绑定的路由，缺了会很难查。
 */
$publicStack = [
    SubstituteBindings::class,
    SiteCacheHeaders::class,
];

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
$applyMode(Route::middleware($publicStack)->controller(SitemapController::class))
    ->group(function (): void {
        // /sitemap.xml 是**索引**，不再直接列 URL。分片的理由不是「太大了」
        // （四百来条离 50000 条上限差得远），是**站长平台按分片单独报收录率**——
        // 城市页铺不铺第二批要靠这个数字决定，混在一份里就看不出来了。
        Route::get('/sitemap.xml', 'sitemap')->name('site.sitemap');
        Route::get('/sitemap-content.xml', 'sitemapContent')->name('site.sitemap.content');
        Route::get('/sitemap-city.xml', 'sitemapCity')->name('site.sitemap.city');
        Route::get('/robots.txt', 'robots')->name('site.robots');
        // 给大模型看的站点索引（llmstxt.org 约定）。与 sitemap 同组同栈：
        // 公开、可缓存、不起 session。已列入 reserved_slugs。
        Route::get('/llms.txt', 'llms')->name('site.llms');
    });

// 内容页。首触归因已搬到客户端 localStorage（#29），这里不再挂归因中间件
$applyMode(Route::middleware($publicStack)->controller(SiteFrontController::class))
    ->group(function () use ($slugPattern): void {
        // 首页
        Route::get('/', 'home')->name('site.home');

        // 装修案例
        Route::get('/cases', 'caseIndex')->name('site.cases.index');
        Route::get('/cases/{slug}', 'caseShow')->name('site.cases.show');

        // 智能方案
        Route::get('/solutions', 'solutionIndex')->name('site.solutions.index');
        Route::get('/solutions/{slug}', 'solutionShow')->name('site.solutions.show');

        // 全屋套餐。**不用加进 reserved_slugs**：/{slug} 在本组的最后注册，
        // 先匹配先赢，一个叫 packages 的静态页永远走不到这里来
        Route::get('/packages', 'packageIndex')->name('site.packages.index');
        Route::get('/packages/{slug}', 'packageShow')->name('site.packages.show');

        // 智能产品
        Route::get('/products', 'productIndex')->name('site.products.index');
        Route::get('/products/{slug}', 'productShow')->name('site.products.show');

        // 站内搜索。已列入 reserved_slugs，/{slug} 不会吞掉它
        Route::get('/search', 'search')->name('site.search');

        // 城市页三段层级。**不用加进 reserved_slugs**，理由同 /packages：
        // /{slug} 在本组最后注册，一个叫 city 的静态页永远走不到这里来。
        //
        // 省页在前、城市页在后，段数不同本不会冲突，但保持「越具体越靠后」的顺序，
        // 读起来就是 URL 的层级本身。
        //
        // ⚠️ /city/{province} 有两种产出：该省级区划自己有城市页就渲染城市页
        // （直辖市），否则渲染下辖城市列表。见 SiteFrontController::cityProvince()。
        Route::get('/city', 'cityIndex')->name('site.city.index');
        Route::get('/city/{province}', 'cityProvince')->name('site.city.province');
        Route::get('/city/{province}/{city}', 'cityShow')->name('site.city.show');

        // 标签聚合。**不用加进 reserved_slugs**，理由同 /packages：本条是两段路径，
        // 单段的 /{slug} 压根匹配不上。
        //
        // 刻意**不注册 /tags 索引页**：全站只有 8 个标签，一页 8 个链接是典型的
        // thin page，且标签页本身已由各内容详情页链到，不缺入口。
        Route::get('/tags/{slug}', 'tagShow')->name('site.tags.show');

        // 资讯：归档先于详情注册。两者段数不同本不会冲突，但保持「越具体越靠前」，
        // 日后若把归档改成 /news/{year}/{month} 也不必回头调顺序。
        Route::get('/news', 'newsIndex')->name('site.news.index');
        Route::get('/news/archive/{year}/{month}', 'newsArchive')
            ->name('site.news.archive')
            ->where(['year' => '[0-9]{4}', 'month' => '0[1-9]|1[0-2]']);
        Route::get('/news/{slug}', 'newsShow')->name('site.news.show');

        // 静态页面（必须最后注册，slug 已排除保留路径，T-10-04-03 参数绑定防注入）
        Route::get('/{slug}', 'page')
            ->name('site.page')
            ->where('slug', $slugPattern);
    });

// 草稿预览（/preview/{type}/{id}，#16，批次 1.5b 起覆盖 SitePage 与批次 1.5a
// 新增状态机的 6 类内容，共 7 类）：**留在 web 组**——授权走「有效签名 或
// 已登录管理员」，后者要 session。preview 已在 reserved_slugs 里，/{slug}
// 不会吞掉它。不挂 signed 中间件：那会把已登录管理员挡在门外。
//
// type 只认 SiteFrontController::PREVIEW_TYPES 里的键，不在表里的一律 404
// （控制器里判断，这里的正则只卡字符集）。
$applyMode(Route::middleware('web')->controller(SiteFrontController::class))
    ->group(function (): void {
        Route::get('/preview/{type}/{id}', 'preview')
            ->name('site.preview')
            ->where('type', '[a-z_]+')
            ->where('id', '[0-9]+');
    });

// 资料下载（gated content）：只接受限时签名链接，签名由询盘提交成功后现签下发。
// **不挂 $publicStack**——SiteCacheHeaders 会给它打 public, max-age，
// 而签名链接是一人一条、限时的，绝不能进任何共享缓存（控制器自己打 private, no-store）。
$applyMode(Route::middleware([ValidateSignature::class])->controller(GatedDownloadController::class))
    ->group(function (): void {
        Route::get('/downloads/{asset}', 'show')
            ->name('site.download')
            ->where('asset', '[a-f0-9]{16}');
    });

// 询盘提交（#29）：无状态端点，不挂 web 组，因此没有 CSRF token 可校验。
// throttle 是粗粒度兜底，精确的每 IP 3 次 / 5 分钟在 Services\ContactSubmission 里。
$applyMode(Route::middleware(['throttle:30,1'])->controller(ContactSubmissionController::class))
    ->group(function (): void {
        Route::post('/contact-submissions', 'store')->name('site.contact.store');
    });

unset($applyMode, $publicStack, $routeConfig, $mode, $domain, $reservedSlugs, $escaped, $slugPattern);
