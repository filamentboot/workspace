<?php

use Filamentboot\FilamentbootSite\Http\Middleware\CaptureVisitorAttribution;
use Filamentboot\FilamentbootSite\SiteServiceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * 访客首触归因测试（SiteAttributionTest）
 *
 * 覆盖场景：
 * - 首次落地时把落地页、来源页与 UTM 写入 session
 * - 后续访问不覆盖已有归因（首触语义：广告落地后跳几个页面再提交，渠道不能丢）
 * - sitemap/robots 不触发归因采集
 *
 * @group site
 *
 * @covers \Filamentboot\FilamentbootSite\Http\Middleware\CaptureVisitorAttribution
 */
beforeEach(function () {
    config([
        'filamentboot-site.route.mode'    => 'root',
        'filamentboot-site.themes'        => ['decoration' => '科技装修（深色）'],
        'filamentboot-site.default_theme' => 'decoration',
    ]);

    $provider = new SiteServiceProvider(app());

    foreach (['registerLivewireComponents', 'registerThemeViews', 'shareSiteSettings', 'registerFrontend'] as $method) {
        $reflection = new ReflectionMethod($provider, $method);
        $reflection->setAccessible(true);
        $reflection->invoke($provider);
    }

    app('router')->getRoutes()->refreshNameLookups();
    app('router')->getRoutes()->refreshActionLookups();
});

/**
 * 首次落地写入落地页、来源页与 UTM
 */
it('首次落地写入归因数据', function () {
    $this->withHeader('referer', 'https://www.baidu.com/s?wd=test')
        ->get('/solutions?utm_source=wechat&utm_medium=cpc&utm_campaign=summer')
        ->assertOk();

    $attribution = session(CaptureVisitorAttribution::SESSION_KEY);

    // fullUrl() 经 Symfony getQueryString() 归一化，查询参数按键排序
    expect($attribution['landing_url'])->toContain('/solutions?')
        ->and($attribution['landing_url'])->toContain('utm_source=wechat')
        ->and($attribution['referer'])->toBe('https://www.baidu.com/s?wd=test')
        ->and($attribution['utm_source'])->toBe('wechat')
        ->and($attribution['utm_medium'])->toBe('cpc')
        ->and($attribution['utm_campaign'])->toBe('summer')
        ->and($attribution['utm_term'])->toBeNull();
});

/**
 * 后续访问不覆盖首次归因
 *
 * 这是「首触」的核心：访客从广告落地后往往要跳几个页面才提交表单，
 * 若每次请求都刷新归因，提交时拿到的会是站内最后一跳，渠道信息全部丢失。
 */
it('后续访问不覆盖首触归因', function () {
    $this->get('/solutions?utm_source=wechat&utm_campaign=summer')->assertOk();

    // 站内继续浏览两个页面（无 UTM）
    $this->get('/cases')->assertOk();
    $this->get('/products')->assertOk();

    $attribution = session(CaptureVisitorAttribution::SESSION_KEY);

    expect($attribution['landing_url'])->toContain('utm_source=wechat')
        ->and($attribution['utm_source'])->toBe('wechat')
        ->and($attribution['utm_campaign'])->toBe('summer');
});

/**
 * 归因数据长度受限，超长 UTM 不会写坏后续入库
 */
it('超长 UTM 参数被截断', function () {
    $this->get('/solutions?utm_source='.str_repeat('a', 400))->assertOk();

    expect(mb_strlen(session(CaptureVisitorAttribution::SESSION_KEY)['utm_source']))->toBe(255);
});

/**
 * sitemap 与 robots 不触发归因采集（不为爬虫开 session）
 */
it('sitemap 与 robots 不采集归因', function () {
    $this->get('/sitemap.xml')->assertOk();
    $this->get('/robots.txt')->assertOk();

    expect(session()->has(CaptureVisitorAttribution::SESSION_KEY))->toBeFalse();
});
