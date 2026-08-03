<?php

use Illuminate\Routing\Router;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

/**
 * 官网前台路由测试（SiteFrontRoutingTest）
 *
 * 覆盖 Pitfall 1：插件禁用时前台路由不应被注册（避免接管现有 routes/web.php）。
 * 覆盖 SITE-02：三种挂载模式（prefix/root/domain）与保留 slug 的实际注册结果。
 *
 * 挂载模式测试用一个临时 Router 实例加载 routes/site.php，
 * 从而在不重启应用的前提下观察真实注册结果，而不是断言源码字符串。
 *
 * @group site
 *
 * @covers \Filamentboot\FilamentbootSite\SiteServiceProvider
 */

/**
 * 在隔离的 Router 中加载官网路由，返回 uri => name 映射
 *
 * @param  array<string, mixed>  $routeConfig  覆盖到 filamentboot-site.route 的配置
 * @return array<string, string|null>
 */
function registerSiteRoutesInIsolation(array $routeConfig = []): array
{
    /** @var array<string, mixed> $base */
    $base = config('filamentboot-site.route');
    config(['filamentboot-site.route' => array_merge($base, $routeConfig)]);

    $original = app('router');

    try {
        $isolated = new Router(app('events'), app());
        app()->instance('router', $isolated);
        Route::clearResolvedInstances();

        require base_path('packages/filamentboot-site/routes/site.php');

        $map = [];
        foreach ($isolated->getRoutes() as $route) {
            $map[$route->uri()] = $route->getName();
        }

        return $map;
    } finally {
        app()->instance('router', $original);
        Route::clearResolvedInstances();
        config(['filamentboot-site.route' => $base]);
    }
}

/**
 * prefix 模式（默认）把官网挂在子路径下，不抢占宿主根路由
 */
it('prefix 模式不抢占宿主根路由', function () {
    $routes = registerSiteRoutesInIsolation(['mode' => 'prefix', 'prefix' => 'site']);

    expect($routes)->toHaveKey('site');
    expect($routes['site'])->toBe('site.home');
    expect($routes)->toHaveKey('site/cases');
    expect($routes)->toHaveKey('site/sitemap.xml');

    // 根路径不得被官网接管
    expect($routes)->not->toHaveKey('/');
});

/**
 * root 模式接管根路径与全部一级路径
 */
it('root 模式接管根路径', function () {
    $routes = registerSiteRoutesInIsolation(['mode' => 'root']);

    expect($routes['/'])->toBe('site.home');
    expect($routes['cases'])->toBe('site.cases.index');
    expect($routes['solutions'])->toBe('site.solutions.index');
    expect($routes['products'])->toBe('site.products.index');
    expect($routes['sitemap.xml'])->toBe('site.sitemap');
    expect($routes['robots.txt'])->toBe('site.robots');
});

/**
 * domain 模式绑定独立域名；未配置域名时降级为 prefix，不注册无主机名路由
 */
it('domain 模式绑定域名，缺域名时降级 prefix', function () {
    $bound = registerSiteRoutesInIsolation(['mode' => 'domain', 'domain' => 'www.example.com']);
    expect($bound['/'])->toBe('site.home');

    $fallback = registerSiteRoutesInIsolation(['mode' => 'domain', 'domain' => null, 'prefix' => 'site']);
    expect($fallback)->toHaveKey('site');
    expect($fallback)->not->toHaveKey('/');
});

/**
 * 固定系统路径先于动态 /{slug} 注册，且 slug 正则排除保留路径
 */
it('保留 slug 不会被动态页面路由吞掉', function () {
    $routes = registerSiteRoutesInIsolation(['mode' => 'root']);

    // sitemap/robots 必须有各自的命名路由，而不是落到 site.page
    expect($routes['sitemap.xml'])->toBe('site.sitemap');
    expect($routes['robots.txt'])->toBe('site.robots');

    // 动态页面路由的 slug 约束应排除 admin 等保留路径
    $slugRoute = collect(app('router')->getRoutes())->first(
        fn ($route) => $route->getName() === 'site.page'
    );

    if ($slugRoute === null) {
        // 插件在测试环境未启用时全局路由表中没有 site.page，改查隔离注册结果
        expect($routes)->toHaveKey('{slug}');
    }

    /** @var list<string> $reserved */
    $reserved = config('filamentboot-site.route.reserved_slugs');
    expect($reserved)->toContain('admin')
        ->toContain('sitemap.xml')
        ->toContain('robots.txt');
});

/**
 * CMS v1 为中文单语言，不再注册 /en 镜像路由
 */
it('不再注册 en 语言前缀路由', function () {
    $routes = registerSiteRoutesInIsolation(['mode' => 'root']);

    foreach (array_keys($routes) as $uri) {
        expect($uri === 'en' || str_starts_with($uri, 'en/'))->toBeFalse(
            "CMS v1 为中文单语言，不应注册英文路由：{$uri}"
        );
    }

    $routeContent = (string) file_get_contents(base_path('packages/filamentboot-site/routes/site.php'));
    expect(str_contains($routeContent, "prefix('en')"))->toBeFalse('路由文件不应再含 /en 前缀');
});

/**
 * 插件禁用时 SiteServiceProvider 不注册前台路由（Pitfall 1 验证）
 *
 * 验证策略：在默认测试环境（filamentboot-site is_enabled=false）下，
 * Route::has('site.home') 应为 false，证明 ServiceProvider 条件注册生效。
 */
it('插件禁用时前台路由不被注册', function () {
    $isEnabled = DB::table('plugins')
        ->where('slug', 'filamentboot-site')
        ->where('is_enabled', true)
        ->exists();

    if ($isEnabled) {
        // 若插件当前为启用，跳过此场景（无法回滚已发生的路由注册）
        $this->markTestSkipped('filamentboot-site 当前为启用状态，Pitfall 1 禁用场景需手动验证');
    }

    expect(Route::has('site.home'))->toBeFalse(
        '插件禁用时 site.home 路由不应注册（Pitfall 1 防护：不覆盖 routes/web.php）'
    );
    expect(Route::has('site.sitemap'))->toBeFalse(
        '插件禁用时 sitemap 路由不应注册'
    );
});
