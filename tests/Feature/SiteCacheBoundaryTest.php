<?php

use Filamentboot\FilamentbootSite\Cms\Enums\PageStatus;
use Filamentboot\FilamentbootSite\Cms\Models\SitePage;
use Filamentboot\FilamentbootSite\Cms\Models\SiteRedirect;
use Filamentboot\FilamentbootSite\Cms\Routing\SiteRedirectMiddleware;
use Filamentboot\FilamentbootSite\SiteServiceProvider;
use Filamentboot\Models\AdminUser;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

/**
 * 公开页缓存边界（#29）
 *
 * 这份文件锁住的是三件很容易被后来的改动静默破坏、且破坏了不会报错的事：
 *
 * 1. **内容页不发 Set-Cookie。** 一旦某处起了 session（加了中间件、视图里调了
 *    csrf_token()、混进一个 Livewire 组件），响应就带会话 Cookie，共享缓存全面失效。
 *    表现只是「CDN 命中率一直是 0」，没有任何异常。
 * 2. **HTML 里没有 Livewire 快照。** Livewire 注入的脚本带 data-csrf → 调
 *    csrf_token() → 起 session，所以第 1 条和这条是同一件事的两面。
 * 3. **带 Cookie 的响应绝不打 public。** 这是最要紧的一条：把带会话 Cookie 的响应
 *    标成公共可缓存，共享缓存会把一个访客的会话发给另一个。
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
        (new ReflectionMethod(SiteServiceProvider::class, $method))->invoke($provider);
    }

    app('router')->getRoutes()->refreshNameLookups();
    app('router')->getRoutes()->refreshActionLookups();
});

/**
 * 内容页：无 Set-Cookie、无 Livewire 快照、带 public max-age
 */
it('内容页不起 session 且可被公共缓存', function (string $path) {
    $response = $this->get($path)->assertOk();

    expect($response->headers->getCookies())->toBe([]);

    expect($response->headers->get('Cache-Control'))
        ->toContain('public')
        ->toContain('max-age='.config('filamentboot-site.cache.public_max_age'));

    $html = $response->getContent();

    expect($html)->not->toContain('wire:snapshot')
        ->and($html)->not->toContain('livewire.js');
})->with(['/', '/cases', '/solutions', '/products', '/news']);

/**
 * 机器读的三个端点用自己的 TTL，不被中间件的默认值顶掉
 *
 * **这三条原先和内容页混在一组，断言的是 max-age=600——而它们的控制器里写的是 3600。**
 * 当时能通过恰恰是因为 SiteCacheHeaders 无条件覆盖，控制器那行等于死代码：
 * 代码写着一小时，发出去的一直是十分钟。三期批次 2 让中间件尊重显式声明之后，
 * 这三条才真正按自己写的走，所以拆出来单独断言。
 *
 * 一小时是合适的：站点地图与 robots 的变化频率远低于内容页，
 * 而爬虫拉它们的频率不低。
 */
it('sitemap / robots / llms 保留控制器自己声明的 TTL', function (string $path) {
    $response = $this->get($path)->assertOk();

    expect($response->headers->getCookies())->toBe([]);

    expect($response->headers->get('Cache-Control'))
        ->toContain('public')
        ->toContain('max-age=3600');
})->with(['/sitemap.xml', '/robots.txt', '/llms.txt']);

/**
 * 带筛选参数的列表页同样可缓存
 *
 * 筛选改成查询串（而不是 Livewire）的收益就在这里：每个组合各自是一个可缓存的静态 URL。
 */
it('带筛选参数的案例列表同样可缓存', function () {
    $response = $this->get('/cases?style=modern&house_type=three_room')->assertOk();

    expect($response->headers->getCookies())->toBe([])
        ->and($response->headers->get('Cache-Control'))->toContain('public');
});

/**
 * 静态页面也走同一套
 */
it('静态页面不起 session', function () {
    SitePage::factory()->create([
        'slug'     => 'cache-boundary-page',
        'title_zh' => '缓存边界页',
        'status'   => PageStatus::PUBLISHED,
        'blocks'   => [['type' => 'hero', 'data' => ['title' => '区块标题']]],
    ]);

    $response = $this->get('/cache-boundary-page')->assertOk();

    expect($response->headers->getCookies())->toBe([])
        ->and($response->headers->get('Cache-Control'))->toContain('public')
        ->and($response->getContent())->toContain('区块标题');
});

/**
 * 404 不缓存
 *
 * 一次误发布导致的 404 被 CDN 缓存住，等于把事故延长到缓存过期。
 */
it('404 不打 public 缓存头', function () {
    $response = $this->get('/this-page-does-not-exist');

    expect($response->getStatusCode())->toBe(404)
        ->and((string) $response->headers->get('Cache-Control'))->not->toContain('public');
});

/**
 * 301 重定向不缓存
 */
it('重定向不打 public 缓存头', function () {
    SiteRedirect::create([
        'from_path'   => 'old-path',
        'to_path'     => '/new-path',
        'status_code' => 301,
    ]);

    app(Kernel::class)
        ->pushMiddleware(SiteRedirectMiddleware::class);

    $response = $this->get('/old-path');

    expect($response->getStatusCode())->toBe(301)
        ->and((string) $response->headers->get('Cache-Control'))->not->toContain('public');
});

/**
 * /preview/{type}/{id} 仍在 web 组：靠 session 认管理员，且**不能**被公共缓存
 *
 * 它是全站唯一读未发布内容的入口。若被标成 public，草稿就会经由共享缓存泄露给公众——
 * 比不缓存严重得多。
 */
it('草稿预览带 session 且绝不打 public 缓存头', function () {
    $page = SitePage::factory()->draft()->create(['slug' => 'preview-cache-page']);

    $user = AdminUser::factory()->create();
    $user->assignRole(
        Role::firstOrCreate([
            'name'       => config('filamentboot.super_admin_role', 'super_admin'),
            'guard_name' => 'admin',
        ])
    );

    $response = $this->actingAs($user, 'admin')->get('/preview/site_page/'.$page->getKey());

    expect($response->getStatusCode())->toBe(200)
        ->and($response->headers->get('X-Robots-Tag'))->toContain('noindex')
        ->and((string) $response->headers->get('Cache-Control'))->not->toContain('public');
});

/**
 * 签名预览链接同样可用（双通道的另一条）
 */
it('签名预览链接仍可访问', function () {
    $page = SitePage::factory()->draft()->create(['slug' => 'signed-preview-page']);

    $url = URL::temporarySignedRoute('site.preview', now()->addMinutes(15), ['type' => 'site_page', 'id' => $page->getKey()]);

    $this->get($url)->assertOk();
});

/**
 * 关掉 max-age 配置后不打缓存头
 */
it('public_max_age 设为 0 时不打缓存头', function () {
    config(['filamentboot-site.cache.public_max_age' => 0]);

    $response = $this->get('/')->assertOk();

    expect((string) $response->headers->get('Cache-Control'))->not->toContain('public');
});
