<?php

use Filamentboot\FilamentbootSite\Cms\Enums\PageStatus;
use Filamentboot\FilamentbootSite\Jobs\PushUrlsToBaidu;
use Filamentboot\FilamentbootSite\Modules\Corporate\Cases\Models\SiteCase;
use Filamentboot\FilamentbootSite\Modules\Corporate\Cities\Models\SiteCityPage;
use Filamentboot\FilamentbootSite\Modules\Corporate\Cities\Models\SiteRegion;
use Filamentboot\FilamentbootSite\Modules\Corporate\Products\Models\SiteProduct;
use Filamentboot\FilamentbootSite\Observers\SearchPushObserver;
use Filamentboot\FilamentbootSite\Services\BaiduPushService;
use Filamentboot\FilamentbootSite\Settings\SiteSettings;
use Filamentboot\FilamentbootSite\SiteServiceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

/**
 * 站长验证与百度主动推送测试（B4）
 *
 * 两条硬约束在这里钉住：
 * 1. 验证串会直接进 <meta content>，非法字符集一律不输出（同 A3 统计 ID 的纪律）
 * 2. 推送只在「发布状态变了且当前可见」时发生，且失败绝不影响内容保存
 *
 * @group site
 */
beforeEach(function () {
    config([
        'filamentboot-site.route.mode'    => 'root',
        'filamentboot-site.themes'        => ['decoration' => '科技装修（浅色）'],
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

    // 观察器在 provider 的 pluginIsEnabled 分支里注册，测试里手工挂
    SiteCase::observe(SearchPushObserver::class);
    SiteProduct::observe(SearchPushObserver::class);
    SiteCityPage::observe(SearchPushObserver::class);
});

afterEach(function () {
    SiteCase::flushEventListeners();
    SiteProduct::flushEventListeners();
    SiteCityPage::flushEventListeners();
});

/**
 * 打开推送开关
 */
function enableBaiduPush(): void
{
    $settings                   = app(SiteSettings::class);
    $settings->baidu_push_token = 'test-token';
    $settings->baidu_push_site  = 'qkznj.com';
    $settings->save();
}

it('填写验证串后输出对应 meta', function () {
    $settings                     = app(SiteSettings::class);
    $settings->baidu_verify_code  = 'code-BAIDU-12345';
    $settings->google_verify_code = 'gsc_verification_abc123';
    $settings->bing_verify_code   = 'BING0123456789AB';
    $settings->save();

    $this->get('/')
        ->assertOk()
        ->assertSee('<meta name="baidu-site-verification" content="code-BAIDU-12345">', false)
        ->assertSee('<meta name="google-site-verification" content="gsc_verification_abc123">', false)
        ->assertSee('<meta name="msvalidate.01" content="BING0123456789AB">', false);
});

it('未填验证串时不输出空 meta', function () {
    $html = (string) $this->get('/')->assertOk()->getContent();

    expect($html)->not->toContain('baidu-site-verification')
        ->and($html)->not->toContain('google-site-verification')
        ->and($html)->not->toContain('msvalidate.01');
});

/**
 * 把整段 <meta> 标签粘进设置框是最常见的填写错误
 *
 * 此时宁可什么都不输出，也不能把半截标签打进 head 破坏文档结构。
 */
it('验证串字符集非法时不输出', function (string $code) {
    $settings                    = app(SiteSettings::class);
    $settings->baidu_verify_code = $code;
    $settings->save();

    expect((string) $this->get('/')->assertOk()->getContent())
        ->not->toContain('baidu-site-verification');
})->with([
    '粘了整段标签' => '<meta name="baidu-site-verification" content="abc" />',
    '带引号'       => 'abc"onload="alert(1)',
    '太短'         => 'abc',
]);

it('未配置 token 时推送服务视为关闭', function () {
    expect(app(BaiduPushService::class)->isEnabled())->toBeFalse();

    Http::fake();

    expect(app(BaiduPushService::class)->push(['https://qkznj.com/a']))->toBe(0);

    Http::assertNothingSent();
});

it('推送成功时返回百度接受的条数', function () {
    enableBaiduPush();

    Http::fake([
        'data.zz.baidu.com/*' => Http::response(['remain' => 2998, 'success' => 2]),
    ]);

    $accepted = app(BaiduPushService::class)->push([
        'https://qkznj.com/a',
        'https://qkznj.com/b',
        // 重复项应在发送前去重
        'https://qkznj.com/a',
    ]);

    expect($accepted)->toBe(2);

    Http::assertSent(function ($request) {
        return str_contains($request->url(), 'site=qkznj.com')
            && $request->body() === "https://qkznj.com/a\nhttps://qkznj.com/b";
    });
});

/**
 * 接口 HTTP 200 但 body 里回错误码，不能当成功
 */
it('百度回错误码时不计入成功数', function () {
    enableBaiduPush();

    Http::fake([
        'data.zz.baidu.com/*' => Http::response(['error' => 401, 'message' => 'token is not valid']),
    ]);

    expect(app(BaiduPushService::class)->push(['https://qkznj.com/a']))->toBe(0);
});

/**
 * 外网不通不能把内容保存打成 500
 */
it('推送接口异常时静默返回 0', function () {
    enableBaiduPush();

    Http::fake(fn () => throw new RuntimeException('connection refused'));

    expect(app(BaiduPushService::class)->push(['https://qkznj.com/a']))->toBe(0);
});

it('内容首次进入已发布态时派发推送任务', function () {
    enableBaiduPush();
    Queue::fake();

    $case = SiteCase::factory()->create([
        'slug'         => 'push-on-publish',
        'published_at' => null,
    ]);

    Queue::assertNothingPushed();

    $case->update(['published_at' => now()->subMinute()]);

    Queue::assertPushed(PushUrlsToBaidu::class);
});

/**
 * 改错别字不该烧配额——百度普通站每天只有 3000 条
 */
it('只改正文不派发推送任务', function () {
    enableBaiduPush();

    $case = SiteCase::factory()->create([
        'slug'         => 'push-on-content-edit',
        'published_at' => now()->subDay(),
    ]);

    Queue::fake();

    $case->update(['title_zh' => '改了个标题']);

    Queue::assertNothingPushed();
});

/**
 * 草稿绝不推送（T-10-04-04 的同一条线）
 */
it('取消发布不派发推送任务', function () {
    enableBaiduPush();

    $product = SiteProduct::factory()->create([
        'slug'         => 'push-unpublish',
        'published_at' => now(),
    ]);

    Queue::fake();

    $product->update(['published_at' => null]);

    Queue::assertNothingPushed();
});

it('未配置 token 时不占用队列', function () {
    Queue::fake();

    SiteCase::factory()->create([
        'slug'         => 'push-disabled',
        'published_at' => now()->subDay(),
    ]);

    Queue::assertNothingPushed();
});

it('回推命令未配置凭据时报错退出', function () {
    $this->artisan('filamentboot-site:push-baidu', ['--all' => true])
        ->expectsOutputToContain('未配置百度推送凭据')
        ->assertExitCode(1);
});

it('回推命令默认只试运行不发请求', function () {
    enableBaiduPush();
    Http::fake();

    SiteCase::factory()->count(2)->create(['published_at' => now()->subDay()]);

    $this->artisan('filamentboot-site:push-baidu')
        ->expectsOutputToContain('试运行')
        ->assertExitCode(0);

    Http::assertNothingSent();
});

/**
 * 城市页没有 slug 列，URL 由 region 关联拼出——这是 2026-08-11 补上的分支，
 * 单独钉住「province/city 两段式」这个与其余六类不同的 URL 形状不会拼错。
 */
it('城市页首次发布时按省市两段式 URL 派发推送任务', function () {
    enableBaiduPush();

    // 不 fake 队列：QUEUE_CONNECTION=sync（phpunit.xml），
    // dispatch 会同步跑到 BaiduPushService::push()，fake Http 断言实际 URL 更直接
    // ——PushUrlsToBaidu::$urls 是 protected，测试闭包访问不到，没有更省事的路。
    Http::fake([
        'data.zz.baidu.com/*' => Http::response(['remain' => 2999, 'success' => 1]),
    ]);

    $province = SiteRegion::factory()->province()->create(['slug' => 'hubei']);
    $city     = SiteRegion::factory()->childOf($province)->create(['slug' => 'wuhan']);
    $cityPage = SiteCityPage::factory()->forRegion($city)->draft()->create();

    Http::assertNothingSent();

    $cityPage->update(['status' => PageStatus::PUBLISHED, 'published_at' => now()->subMinute()]);

    Http::assertSent(fn ($request) => str_contains($request->body(), '/city/hubei/wuhan'));
});

/**
 * 城市页没有 slug 列，allPublishedUrls() 的 pluck('slug') 那条通用路径吃不了它，
 * 必须走单独分支——这里钉住 --all 回推命令不会因为多了城市页就崩掉。
 */
it('回推命令 --all 能带上城市页且不因缺 slug 列崩掉', function () {
    enableBaiduPush();

    Http::fake([
        'data.zz.baidu.com/*' => Http::response(['remain' => 2990, 'success' => 1]),
    ]);

    $province = SiteRegion::factory()->province()->create(['slug' => 'jiangsu']);
    $city     = SiteRegion::factory()->childOf($province)->create(['slug' => 'nanjing']);
    SiteCityPage::factory()->forRegion($city)->create(['published_at' => now()->subDay()]);

    $this->artisan('filamentboot-site:push-baidu', ['--all' => true])
        ->assertExitCode(0);

    Http::assertSent(fn ($request) => str_contains($request->body(), '/city/jiangsu/nanjing'));
});

it('回推命令加 --all 时推送全部已发布内容', function () {
    enableBaiduPush();

    Http::fake([
        'data.zz.baidu.com/*' => Http::response(['remain' => 2990, 'success' => 2]),
    ]);

    SiteCase::factory()->create(['slug' => 'pushed-a', 'published_at' => now()->subDay()]);
    SiteCase::factory()->create(['slug' => 'pushed-draft', 'published_at' => null]);
    SiteProduct::factory()->create(['slug' => 'pushed-b', 'published_at' => now()]);

    $this->artisan('filamentboot-site:push-baidu', ['--all' => true])
        ->assertExitCode(0);

    Http::assertSent(function ($request) {
        $body = $request->body();

        return str_contains($body, '/cases/pushed-a')
            && str_contains($body, '/products/pushed-b')
            // 草稿不进推送清单
            && ! str_contains($body, 'pushed-draft');
    });
});
