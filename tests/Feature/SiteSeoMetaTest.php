<?php

use Filamentboot\FilamentbootSite\Models\SiteCase;
use Filamentboot\FilamentbootSite\Settings\SiteSettings;
use Filamentboot\FilamentbootSite\SiteServiceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * SEO 元数据测试（SiteSeoMetaTest）
 *
 * 覆盖场景：
 * - SEO 回退链：记录 seo_title → 标题 → 全局默认 → app.name（Pattern 5）
 * - 列表页 meta description 永不为空（线上 P0：首页/列表页 description 为空）
 * - 站点设置真实注入前台视图（此前控制器传 $settings、视图读 $siteSettings，从未生效）
 * - 未配置 OG 图时不输出 og:image（此前硬编码到不存在的 /img/og-default.jpg）
 * - 未发布内容不泄露（T-10-04-04）
 *
 * 测试一律走真实 HTTP 路由：上一版本直接给视图手工注入 'siteSettings' => null，
 * 恰好绕开了控制器与视图之间的变量名不一致，导致该 bug 长期不被发现。
 *
 * @group site
 */

/**
 * 用 SiteServiceProvider 的真实注册路径挂载前台资源
 *
 * 复用生产代码而非在测试里重写一份注册逻辑，避免两边行为漂移。
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

    // 生产环境由 RouteServiceProvider 的 app->booted 回调统一刷新路由名查找表；
    // 测试里 provider 是在应用 boot 之后手工调用的，需显式刷新才能用 route() 生成 URL。
    app('router')->getRoutes()->refreshNameLookups();
    app('router')->getRoutes()->refreshActionLookups();
});

/**
 * 案例详情页输出记录自身的 SEO 字段（回退链第一层）
 */
it('案例详情页输出记录自身的 SEO meta', function () {
    SiteCase::factory()->create([
        'title_zh'        => '现代简约客厅装修案例',
        'slug'            => 'modern-living-room-case',
        'seo_title'       => '现代简约客厅 - 晴空妙享智能家居',
        'seo_description' => '这是案例专属 SEO 描述，满足 160 字符以内。',
        'seo_keywords'    => '现代简约,客厅装修,智能家居',
        'published_at'    => now()->subDay(),
    ]);

    $response = $this->get('/cases/modern-living-room-case');

    $response->assertOk();
    $response->assertSee('现代简约客厅 - 晴空妙享智能家居', escape: false);
    $response->assertSee('这是案例专属 SEO 描述，满足 160 字符以内。', escape: false);
    $response->assertSee('现代简约,客厅装修,智能家居', escape: false);
    $response->assertSee('<meta property="og:title"', escape: false);
    $response->assertSee('rel="canonical"', escape: false);
});

/**
 * 无 seo_title 时回退到标题字段（回退链第二层）
 */
it('无记录 SEO 字段时回退到标题', function () {
    SiteCase::factory()->create([
        'title_zh'        => '北欧风格卧室改造',
        'slug'            => 'nordic-bedroom-renovation',
        'seo_title'       => '',
        'seo_description' => '',
        'published_at'    => now()->subDay(),
    ]);

    $response = $this->get('/cases/nordic-bedroom-renovation');

    $response->assertOk();
    $response->assertSee('北欧风格卧室改造', escape: false);
});

/**
 * 列表页 meta description 永不为空
 *
 * 线上 P0：首页、案例、方案、产品列表页的 meta description 全部为空字符串。
 * 站点设置未填写默认描述时，必须回退到 config 兜底文案。
 */
it('列表页 meta description 不为空', function () {
    $settings                             = app(SiteSettings::class);
    $settings->seo_default_description_zh = '';
    $settings->save();

    foreach (['/', '/cases', '/solutions', '/products'] as $path) {
        $html = $this->get($path)->assertOk()->getContent();

        preg_match('/<meta name="description" content="([^"]*)"/', (string) $html, $matches);

        expect($matches[1] ?? '')->not->toBe('', "页面 {$path} 的 meta description 不应为空");
    }
});

/**
 * 站点设置真实注入前台视图
 *
 * 控制器与 Blade 的变量名一旦再次错位，本用例立即失败。
 */
it('站点设置注入前台视图并渲染', function () {
    $settings                             = app(SiteSettings::class);
    $settings->company_name_zh            = '测试科技有限公司';
    $settings->phone                      = '027-9999-8888';
    $settings->address_zh                 = '湖北省武汉市测试路 1 号';
    $settings->icp_number                 = '鄂ICP备12345678号';
    $settings->seo_default_description_zh = '来自站点设置的默认描述。';
    $settings->save();

    $response = $this->get('/');

    $response->assertOk();
    $response->assertSee('测试科技有限公司', escape: false);
    $response->assertSee('027-9999-8888', escape: false);
    $response->assertSee('湖北省武汉市测试路 1 号', escape: false);
    $response->assertSee('鄂ICP备12345678号', escape: false);
    $response->assertSee('来自站点设置的默认描述。', escape: false);
});

/**
 * 联系方式全部未配置时页脚不渲染空的「联系我们」栏目
 */
it('联系方式未配置时页脚不渲染空栏目', function () {
    $settings                = app(SiteSettings::class);
    $settings->phone         = '';
    $settings->address_zh    = '';
    $settings->wechat_qrcode = null;
    $settings->save();

    $html = (string) $this->get('/')->assertOk()->getContent();

    expect(substr_count($html, '联系我们'))->toBeGreaterThan(0); // 导航/CTA 中仍有
    expect($html)->not->toContain('tracking-widest mb-4">联系我们');
});

/**
 * 未配置默认 OG 图时不输出 og:image
 *
 * 此前硬编码 asset('img/og-default.jpg')，该文件不存在，线上 og:image 恒为 404。
 */
it('未配置 OG 图时不输出 og:image', function () {
    $settings                   = app(SiteSettings::class);
    $settings->og_default_image = null;
    $settings->save();

    $html = (string) $this->get('/')->assertOk()->getContent();

    expect($html)->not->toContain('og:image');
    expect($html)->not->toContain('img/og-default.jpg');
});

/**
 * 前台不再输出任何外部占位图
 */
it('前台不输出外部占位图服务地址', function () {
    SiteCase::factory()->count(3)->create(['published_at' => now()->subDay()]);

    foreach (['/', '/cases'] as $path) {
        $html = (string) $this->get($path)->assertOk()->getContent();

        expect($html)->not->toContain('picsum.photos');
        expect($html)->not->toContain('unsplash.com');
    }
});

/**
 * 分页页面的 canonical 自指，保留 page 参数
 *
 * 此前 canonical 取 url()->current()（不含查询串），/solutions?page=2 的 canonical
 * 指向 /solutions，等于声明列表页第 2 页往后都是第 1 页的副本，深层内容不被索引。
 */
it('分页列表页 canonical 保留 page 参数', function () {
    $html = (string) $this->get('/solutions?page=2')->assertOk()->getContent();

    preg_match('/<link rel="canonical" href="([^"]*)"/', $html, $matches);

    expect($matches[1] ?? '')->toContain('page=2');
});

/**
 * canonical 剥离广告与统计平台的追踪参数
 *
 * 追踪参数不改变页面内容，留在 canonical 里会让同一页面产生无数个「不同」URL。
 */
it('canonical 剥离追踪参数但保留分页参数', function () {
    $html = (string) $this->get('/solutions?utm_source=wechat&utm_medium=cpc&gclid=abc&page=3')
        ->assertOk()
        ->getContent();

    preg_match('/<link rel="canonical" href="([^"]*)"/', $html, $matches);
    $canonical = $matches[1] ?? '';

    expect($canonical)->toContain('page=3')
        ->and($canonical)->not->toContain('utm_source')
        ->and($canonical)->not->toContain('utm_medium')
        ->and($canonical)->not->toContain('gclid');
});

/**
 * 无查询串时 canonical 不追加空的问号
 */
it('无查询串时 canonical 不带问号', function () {
    $html = (string) $this->get('/solutions')->assertOk()->getContent();

    preg_match('/<link rel="canonical" href="([^"]*)"/', $html, $matches);

    expect($matches[1] ?? '')->not->toContain('?');
});

/**
 * 未发布内容不泄露（T-10-04-04 安全验证）
 */
it('未发布案例不被前台展示', function () {
    SiteCase::factory()->draft()->create(['slug' => 'draft-case-should-not-appear']);
    SiteCase::factory()->create([
        'slug'         => 'published-case-visible',
        'published_at' => now()->subDay(),
    ]);

    $this->get('/cases/published-case-visible')->assertOk();
    $this->get('/cases/draft-case-should-not-appear')->assertNotFound();
});
