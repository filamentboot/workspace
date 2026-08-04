<?php

use Filamentboot\FilamentbootSite\Cms\Models\SitePage;
use Filamentboot\FilamentbootSite\Cms\Models\SiteRedirect;
use Filamentboot\FilamentbootSite\Cms\Routing\SiteRedirectMiddleware;
use Filamentboot\FilamentbootSite\SiteServiceProvider;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

uses(RefreshDatabase::class);

/**
 * 旧 URL 301 重定向测试（#18）
 *
 * 覆盖场景：
 * - 命中重定向返回 301 并递增 hits
 * - 路径归一：/old、old/、/old?utm_source=x 命中同一条
 * - 挂载路径早退：宿主自己的路由不查库、不被拦
 * - to_path 被 scheme 白名单拦下时当作没配（跳到不安全地址比 404 严重）
 * - 非安全方法（POST）不跳转
 * - 自指重定向不建不跳
 *
 * 中间件是全局中间件，测试里手工把它挂到测试路由上并注册前台路由。
 *
 * @group site
 */
beforeEach(function () {
    config([
        'filamentboot-site.route.mode'    => 'root',
        'filamentboot-site.default_theme' => 'decoration',
    ]);

    $provider = new SiteServiceProvider(app());

    foreach (['registerThemeViews', 'shareSiteSettings', 'registerFrontend'] as $method) {
        $reflection = new ReflectionMethod($provider, $method);
        $reflection->setAccessible(true);
        $reflection->invoke($provider);
    }

    // 全局中间件在测试里不会被 SiteServiceProvider 的 callAfterResolving 挂上
    // （Kernel 早已解析完），因此显式推入
    app(Kernel::class)->pushMiddleware(SiteRedirectMiddleware::class);

    app('router')->getRoutes()->refreshNameLookups();
    app('router')->getRoutes()->refreshActionLookups();
});

/**
 * 命中重定向返回 301 并指向新地址
 */
it('旧路径 301 跳转到新地址', function () {
    SiteRedirect::create(['from_path' => 'old-about', 'to_path' => '/about', 'status_code' => 301]);

    $this->get('/old-about')
        ->assertStatus(301)
        ->assertRedirect('/about');
});

/**
 * 302 也支持
 */
it('支持 302 临时跳转', function () {
    SiteRedirect::create(['from_path' => 'temp', 'to_path' => '/about', 'status_code' => 302]);

    $this->get('/temp')->assertStatus(302);
});

/**
 * 非法状态码回落 301
 */
it('非法状态码回落 301', function () {
    SiteRedirect::create(['from_path' => 'weird', 'to_path' => '/about', 'status_code' => 418]);

    $this->get('/weird')->assertStatus(301);
});

/**
 * 每次命中递增 hits
 *
 * hits 走 DB::increment 单条 UPDATE，不走模型（省一次 SELECT 与全部模型事件）。
 */
it('命中后 hits 递增', function () {
    $redirect = SiteRedirect::create(['from_path' => 'counted', 'to_path' => '/about']);

    // hits 有数据库默认值 0，新建后需 refresh 才在内存里
    expect($redirect->refresh()->hits)->toBe(0);

    $this->get('/counted');
    $this->get('/counted');
    $this->get('/counted');

    expect($redirect->refresh()->hits)->toBe(3);
});

/**
 * 路径归一：三种写法命中同一条记录
 */
it('路径归一后命中同一条记录', function (string $path) {
    SiteRedirect::create(['from_path' => 'old-page', 'to_path' => '/about']);

    $this->get($path)->assertStatus(301)->assertRedirect('/about');
})->with([
    '带前导斜杠' => ['/old-page'],
    '带尾随斜杠' => ['/old-page/'],
    '带查询串'   => ['/old-page?utm_source=wechat'],
]);

/**
 * normalizePath 的单元级断言
 */
it('normalizePath 去掉斜杠与查询串', function () {
    expect(SiteRedirectMiddleware::normalizePath('/old/'))->toBe('old')
        ->and(SiteRedirectMiddleware::normalizePath('old'))->toBe('old')
        ->and(SiteRedirectMiddleware::normalizePath('/a/b/?x=1'))->toBe('a/b')
        ->and(SiteRedirectMiddleware::normalizePath('/a#frag'))->toBe('a')
        ->and(SiteRedirectMiddleware::normalizePath('/'))->toBe('');
});

/**
 * 未命中的路径正常走后续路由
 */
it('未命中重定向时正常渲染页面', function () {
    SitePage::factory()->create(['slug' => 'real-page', 'title_zh' => '真实页面']);

    $this->get('/real-page')->assertOk()->assertSee('真实页面', escape: false);
});

/**
 * to_path 是伪协议时当作没配，请求继续走正常路由
 *
 * 跳到一个不安全地址比 404 严重得多。
 */
it('不安全的 to_path 不跳转', function () {
    SitePage::factory()->create(['slug' => 'safe-page', 'title_zh' => '安全页面']);
    SiteRedirect::create(['from_path' => 'safe-page', 'to_path' => 'javascript:alert(1)']);

    $this->get('/safe-page')->assertOk()->assertSee('安全页面', escape: false);
});

/**
 * 不带 scheme 的相对目标补上前导斜杠
 */
it('相对目标补前导斜杠', function () {
    SiteRedirect::create(['from_path' => 'rel', 'to_path' => 'about']);

    $this->get('/rel')->assertRedirect('/about');
});

/**
 * 完整外链可作为目标
 */
it('外链目标可用', function () {
    SiteRedirect::create(['from_path' => 'ext', 'to_path' => 'https://example.com/x']);

    $this->get('/ext')->assertRedirect('https://example.com/x');
});

/**
 * POST 到旧地址不跳转：跳过去会丢请求体
 */
it('POST 请求不跳转', function () {
    SiteRedirect::create(['from_path' => 'form-target', 'to_path' => '/about']);

    // 没有对应的 POST 路由，所以期望 405/404 而不是 301
    $this->post('/form-target')->assertStatus(405);
});

/**
 * root 模式下保留 slug 前缀的请求不查重定向表
 *
 * 后台每个 Livewire 轮询都会打到 /livewire/update，让它们也过一次查询纯属浪费。
 */
it('保留路径不查重定向表', function () {
    SiteRedirect::create(['from_path' => 'livewire/update', 'to_path' => '/about']);

    DB::enableQueryLog();

    Route::get('/livewire/update', fn (): string => 'livewire ok');

    $this->get('/livewire/update')->assertOk()->assertSee('livewire ok');

    $queries = collect(DB::getQueryLog())->pluck('query')->implode(' ');

    DB::disableQueryLog();

    expect($queries)->not->toContain('site_redirects');
});

/**
 * prefix 模式下官网范围外的路径不被拦
 *
 * 判据用 hits 而不是响应状态码：beforeEach 里前台路由是按 root 模式注册的，
 * 范围外的路径会被官网自己的 /{slug} 兜底路由接走并 404——那个 404 不能证明
 * 中间件放行了。hits 保持 0 才说明中间件早退，连查询都没发。
 */
it('prefix 模式下范围外路径不被拦', function () {
    config([
        'filamentboot-site.route.mode'   => 'prefix',
        'filamentboot-site.route.prefix' => 'site',
    ]);

    $redirect = SiteRedirect::create(['from_path' => 'host-route', 'to_path' => '/about']);

    $this->get('/host-route')->assertStatus(404);

    expect($redirect->refresh()->hits)->toBe(0);
});

/**
 * prefix 模式下官网范围内的路径正常跳转
 */
it('prefix 模式下范围内路径正常跳转', function () {
    config([
        'filamentboot-site.route.mode'   => 'prefix',
        'filamentboot-site.route.prefix' => 'site',
    ]);

    SiteRedirect::create(['from_path' => 'site/old', 'to_path' => '/site/new']);

    $this->get('/site/old')->assertStatus(301)->assertRedirect('/site/new');
});

/**
 * domain 模式下只拦绑定域名的请求
 */
it('domain 模式下只拦绑定域名', function () {
    config([
        'filamentboot-site.route.mode'   => 'domain',
        'filamentboot-site.route.domain' => 'www.example.com',
    ]);

    $redirect = SiteRedirect::create(['from_path' => 'old-domain-page', 'to_path' => '/new']);

    // 非绑定域名 → 中间件早退，hits 不动（同上，404 来自官网自己的兜底路由）
    $this->get('/old-domain-page')->assertStatus(404);

    expect($redirect->refresh()->hits)->toBe(0);

    // 绑定域名 → 跳转
    $this->get('http://www.example.com/old-domain-page')->assertStatus(301);

    expect($redirect->refresh()->hits)->toBe(1);
});

/**
 * 首页（归一化后为空路径）不参与重定向
 */
it('首页不参与重定向', function () {
    SiteRedirect::create(['from_path' => '', 'to_path' => '/about']);

    $this->get('/')->assertOk();
});
