<?php

use Filamentboot\FilamentbootSite\Http\Middleware\CaptureVisitorAttribution;
use Filamentboot\FilamentbootSite\SiteServiceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * 访客首触归因测试（#29 起归因在客户端）
 *
 * 原来这件事由 `CaptureVisitorAttribution` 中间件在服务端做、写 session。#29 把它搬到
 * 客户端 localStorage（`shared/components/attribution-store.blade.php`），因为公开页要能
 * 整页缓存就不能起 session——起了就有 Set-Cookie，共享缓存直接失效。
 *
 * 因此本文件只能验两件事：
 * 1. 归因脚本真的被注入到每个公开页，且「只在 key 不存在时写一次」的首触语义写在里面
 * 2. 服务端不再往 session 写任何归因（回归护栏——中间件真的退役了）
 *
 * 「首触值算得对不对」是浏览器行为，Feature 测试碰不到，归 Playwright
 * （`tests/e2e/uat-phase12.spec.cjs` 的归因用例）。而「归因怎么从请求体落到列上」
 * 在 `ContactFormTest` 里。
 *
 * @group site
 */
beforeEach(function () {
    config([
        'filamentboot-site.route.mode'    => 'root',
        'filamentboot-site.themes'        => ['decoration' => '科技装修（深色）'],
        'filamentboot-site.default_theme' => 'decoration',
    ]);

    $provider = new SiteServiceProvider(app());

    foreach (['registerThemeViews', 'shareSiteSettings', 'registerFrontend'] as $method) {
        $reflection = new ReflectionMethod($provider, $method);
        $reflection->setAccessible(true);
        $reflection->invoke($provider);
    }

    app('router')->getRoutes()->refreshNameLookups();
    app('router')->getRoutes()->refreshActionLookups();
});

/**
 * 归因脚本注入到公开页，且带着首触语义
 */
it('公开页注入客户端归因脚本', function (string $path) {
    $html = $this->get($path)->assertOk()->getContent();

    expect($html)->toContain("Alpine.store('siteAttribution'")
        // 首触语义：只在读不到时写一次
        ->and($html)->toContain('if (read() === null)')
        // 采集的字段
        ->and($html)->toContain('landing_url')
        ->and($html)->toContain('utm_campaign')
        // localStorage 不可用时降级为内存，而不是整段抛异常把表单搞坏
        ->and($html)->toContain('memory');
})->with(['/', '/cases', '/solutions', '/products', '/news']);

/**
 * 服务端不再往 session 写归因（回归护栏）
 *
 * 中间件退役后若有人把它加回来，公开页就会重新起 session、整页缓存静默失效——
 * 那种失效不会有任何报错，只会表现为「CDN 命中率一直是 0」。
 */
it('公开页不再往 session 写归因', function () {
    $this->withHeader('referer', 'https://www.baidu.com/s?wd=test')
        ->get('/solutions?utm_source=wechat&utm_medium=cpc')
        ->assertOk();

    expect(session()->has('site.attribution'))->toBeFalse()
        ->and(session()->all())->not->toHaveKey('site.attribution');
});

/**
 * 归因中间件类已删除
 */
it('归因中间件类已不存在', function () {
    expect(class_exists(CaptureVisitorAttribution::class))
        ->toBeFalse();
});
