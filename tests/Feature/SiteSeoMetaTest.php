<?php

use Filamentboot\FilamentbootSite\Cms\Models\SitePage;
use Filamentboot\FilamentbootSite\Modules\Corporate\Banners\Models\SiteBanner;
use Filamentboot\FilamentbootSite\Modules\Corporate\Cases\Models\SiteCase;
use Filamentboot\FilamentbootSite\Modules\Corporate\Cities\Models\SiteCityPage;
use Filamentboot\FilamentbootSite\Modules\Corporate\Cities\Models\SiteRegion;
use Filamentboot\FilamentbootSite\Modules\Corporate\Products\Models\SiteProduct;
use Filamentboot\FilamentbootSite\Modules\News\Models\NewsArticle;
use Filamentboot\FilamentbootSite\Settings\SiteSettings;
use Filamentboot\FilamentbootSite\SiteServiceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

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
        'filamentboot-site.themes'        => ['decoration' => '科技装修（浅色）'],
        'filamentboot-site.default_theme' => 'decoration',
    ]);

    $provider = new SiteServiceProvider(app());

    foreach (['registerThemeViews', 'shareSiteSettings', 'registerFrontend'] as $method) {
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
 * 首页与列表页输出全局默认关键词
 *
 * 这两类页面没有对应内容记录，控制器此前给 keywords 写死空串，
 * meta 关键词标签从来没在承载站点主词的页面上出现过。
 */
it('首页与列表页输出全局默认关键词', function () {
    $settings                          = app(SiteSettings::class);
    $settings->seo_default_keywords_zh = '全屋智能家居 智能开关 智能窗帘';
    $settings->save();

    foreach (['/', '/cases', '/solutions', '/products', '/news'] as $path) {
        $this->get($path)->assertOk()
            ->assertSee('<meta name="keywords" content="全屋智能家居 智能开关 智能窗帘">', escape: false);
    }
});

/**
 * 记录自身关键词优先于全局默认
 */
it('记录自填关键词不被全局默认覆盖', function () {
    $settings                          = app(SiteSettings::class);
    $settings->seo_default_keywords_zh = '全局默认词';
    $settings->save();

    SiteCase::factory()->create([
        'title_zh'     => '带关键词的案例',
        'slug'         => 'case-with-keywords',
        'seo_keywords' => '案例自填词',
        'published_at' => now()->subDay(),
    ]);

    $this->get('/cases/case-with-keywords')->assertOk()
        ->assertSee('<meta name="keywords" content="案例自填词">', escape: false)
        ->assertDontSee('全局默认词', escape: false);
});

/**
 * 记录未填关键词时回退到全局默认
 */
it('记录未填关键词时回退到全局默认', function () {
    $settings                          = app(SiteSettings::class);
    $settings->seo_default_keywords_zh = '全局默认词';
    $settings->save();

    SiteCase::factory()->create([
        'title_zh'     => '无关键词的案例',
        'slug'         => 'case-without-keywords',
        'seo_keywords' => '',
        'published_at' => now()->subDay(),
    ]);

    $this->get('/cases/case-without-keywords')->assertOk()
        ->assertSee('<meta name="keywords" content="全局默认词">', escape: false);
});

/**
 * 默认关键词留空时整条 meta 不输出（与新增该字段之前的行为一致）
 */
it('未配置默认关键词时不输出 keywords 标签', function () {
    $settings                          = app(SiteSettings::class);
    $settings->seo_default_keywords_zh = '';
    $settings->save();

    $this->get('/')->assertOk()->assertDontSee('<meta name="keywords"', escape: false);
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
 * 从 HTML 中取出 JSON-LD 并解码
 *
 * @return array<string, mixed>|null
 */
function extractJsonLd(string $html): ?array
{
    if (preg_match('#<script type="application/ld\+json">(.*?)</script>#s', $html, $matches) !== 1) {
        return null;
    }

    /** @var array<string, mixed>|null $decoded */
    $decoded = json_decode($matches[1], true);

    return $decoded;
}

/**
 * 提取页面上全部 JSON-LD 节点，按 @type 归档
 *
 * B1 之后一个页面可以有多个节点（详情页 = Article/Product + BreadcrumbList），
 * extractJsonLd() 只取第一个，断言第二个节点时用这个。
 *
 * @return array<string, array<string, mixed>>
 */
function extractJsonLdByType(string $html): array
{
    preg_match_all('#<script type="application/ld\+json">(.*?)</script>#s', $html, $matches);

    $nodes = [];

    foreach ($matches[1] as $raw) {
        /** @var array<string, mixed>|null $decoded */
        $decoded = json_decode($raw, true);

        if (is_array($decoded) && isset($decoded['@type'])) {
            $nodes[(string) $decoded['@type']] = $decoded;
        }
    }

    return $nodes;
}

/**
 * 案例详情页输出 Article 结构化数据
 */
it('案例详情页输出 Article JSON-LD', function () {
    SiteCase::factory()->create([
        'title_zh'     => '光谷保利时区全屋智能',
        'slug'         => 'json-ld-case',
        'seo_title'    => '光谷全屋智能改造实录',
        'published_at' => now()->subDay(),
    ]);

    $schema = extractJsonLd((string) $this->get('/cases/json-ld-case')->assertOk()->getContent());

    expect($schema)->not->toBeNull()
        ->and($schema['@context'])->toBe('https://schema.org')
        ->and($schema['@type'])->toBe('Article')
        ->and($schema['headline'])->toBe('光谷全屋智能改造实录')
        ->and($schema['datePublished'])->not->toBeEmpty()
        ->and($schema['mainEntityOfPage']['@id'])->toContain('/cases/json-ld-case');
});

/**
 * 产品详情页输出 Product 结构化数据（含 offers 与 brand）
 */
it('产品详情页输出 Product JSON-LD', function () {
    SiteProduct::factory()->create([
        'title_zh'     => '双模网关',
        'slug'         => 'json-ld-product',
        'seo_title'    => '',
        'brand'        => '晴空妙享',
        'price'        => 899.00,
        'published_at' => now(),
    ]);

    $schema = extractJsonLd((string) $this->get('/products/json-ld-product')->assertOk()->getContent());

    expect($schema)->not->toBeNull()
        ->and($schema['@type'])->toBe('Product')
        ->and($schema['name'])->toBe('双模网关')
        ->and($schema['brand']['name'])->toBe('晴空妙享')
        ->and($schema['offers']['price'])->toBe('899.00')
        ->and($schema['offers']['priceCurrency'])->toBe('CNY');
});

/**
 * 无价格产品不输出 offers
 *
 * 缺 offers 只会让 Google 降级为普通 Product（告警），
 * 而填一个假价格属于结构化数据造假，风险大得多。
 */
it('无价格产品不输出 offers 节点', function () {
    SiteProduct::factory()->create([
        'title_zh'     => '定制中控屏',
        'slug'         => 'json-ld-no-price',
        'price'        => null,
        'published_at' => now(),
    ]);

    $schema = extractJsonLd((string) $this->get('/products/json-ld-no-price')->assertOk()->getContent());

    expect($schema)->not->toBeNull()
        ->and($schema['@type'])->toBe('Product')
        ->and($schema)->not->toHaveKey('offers');
});

/**
 * 列表页输出 CollectionPage 结构化数据
 *
 * **这条原先断言的是「列表页什么都不输出」，三期批次 2 改了。** 当时的理由是
 * 「结构化数据只在有具体实体的详情页才有意义」，但 CollectionPage 恰恰是
 * schema.org 为列表页准备的类型，声明它比留白更能让搜索引擎理解站点结构。
 *
 * isPartOf 指回首页 WebSite 节点的 @id，几个顶层节点因此连成一张图。
 *
 * ⚠️ 有意不输出 url：列表页带筛选参数时 canonical 指向带参地址，而控制器
 * 能拿到的只有不含查询串的路由地址，两者对不上就是自相矛盾的信号。
 */
it('列表页输出 CollectionPage JSON-LD', function () {
    foreach (['/cases', '/products', '/solutions', '/news', '/packages'] as $path) {
        $nodes = extractJsonLdByType((string) $this->get($path)->assertOk()->getContent());

        // 断言前先自证：缺了直接 fail 并带上是哪一页，比 undefined key 好查
        expect(array_keys($nodes))->toContain('CollectionPage');

        $schema = $nodes['CollectionPage'];

        expect($schema['name'])->not->toBeEmpty()
            ->and($schema['description'])->not->toBeEmpty()
            ->and($schema['inLanguage'])->toBe('zh-CN')
            ->and($schema['isPartOf']['@id'])->toBe(route('site.home').'#website')
            ->and($schema)->not->toHaveKey('url');
    }
});

/**
 * 站内搜索页不输出 CollectionPage
 *
 * 搜索页也走 buildListSeo()，但它是 noindex 且 URL 空间无限的（任意关键词
 * × 任意组合），给它声明 CollectionPage 只会制造成千上万个低价值实体声明。
 * 这是 buildListSeo() 的 $collection 参数默认关闭的原因。
 */
it('搜索页不输出 CollectionPage', function () {
    $nodes = extractJsonLdByType((string) $this->get('/search?q=灯')->assertOk()->getContent());

    expect($nodes)->not->toHaveKey('CollectionPage');
});

/**
 * 首页输出 Organization 结构化数据（B1）
 *
 * 品牌词搜索的知识面板锚在首页，所以顶层 Organization 只在这里出一次；
 * 详情页里它已经作为 publisher / author 嵌在 Article 与 Product 内部。
 */
it('首页输出 Organization JSON-LD', function () {
    app(SiteSettings::class)->fill([
        'company_name_zh' => '湖北晴空妙享科技有限公司',
        'phone'           => '027-88889999',
        'address_zh'      => '武汉市洪山区光谷大道 3 号',
    ])->save();

    $schema = extractJsonLd((string) $this->get('/')->assertOk()->getContent());

    expect($schema)->not->toBeNull()
        ->and($schema['@type'])->toBe('Organization')
        ->and($schema['name'])->toBe('湖北晴空妙享科技有限公司')
        ->and($schema['telephone'])->toBe('027-88889999')
        ->and($schema['address']['streetAddress'])->toBe('武汉市洪山区光谷大道 3 号')
        ->and($schema)->toHaveKey('url');
});

/**
 * 站点设置留空的字段不进 Organization
 *
 * 结构化数据里出现空字符串比缺字段更糟，会被判为无效值。
 */
it('Organization 不输出未填写的字段', function () {
    app(SiteSettings::class)->fill([
        'phone'      => '',
        'address_zh' => '',
    ])->save();

    $schema = extractJsonLd((string) $this->get('/')->assertOk()->getContent());

    expect($schema['@type'])->toBe('Organization')
        ->and($schema)->not->toHaveKey('telephone')
        ->and($schema)->not->toHaveKey('address');
});

/**
 * 详情页同时输出实体节点与 BreadcrumbList（B1 + B3）
 *
 * 这一例同时锁住 seo-meta 的多节点输出：改回单节点写法后，
 * BreadcrumbList 会被静默丢掉而 Product 仍在，只断言 Product 是发现不了的。
 */
it('产品详情页同时输出 Product 与 BreadcrumbList', function () {
    SiteProduct::factory()->create([
        'title_zh'     => '智能中控面板 S1',
        'slug'         => 'json-ld-breadcrumb',
        'published_at' => now(),
    ]);

    $nodes = extractJsonLdByType((string) $this->get('/products/json-ld-breadcrumb')->assertOk()->getContent());

    expect($nodes)->toHaveKeys(['Product', 'BreadcrumbList']);

    $items = $nodes['BreadcrumbList']['itemListElement'];

    expect($items)->toHaveCount(3)
        ->and($items[0]['name'])->toBe('首页')
        ->and($items[0]['position'])->toBe(1)
        ->and($items[1]['name'])->toBe('智能产品')
        ->and($items[2]['name'])->toBe('智能中控面板 S1')
        ->and($items[2]['position'])->toBe(3)
        // 当前页 url 为 null，item 必须由当前 URL 补齐，否则整段判无效
        ->and($items[2]['item'])->toContain('/products/json-ld-breadcrumb');
});

/**
 * 未归类资讯的面包屑跳过分类层
 *
 * 否则会留一个指向 /news?category= 的空链接。
 */
it('未归类资讯的面包屑只有三级', function () {
    NewsArticle::factory()->create([
        'title_zh'     => '无分类资讯',
        'slug'         => 'json-ld-uncategorised',
        'category_id'  => null,
        'published_at' => now()->subDay(),
    ]);

    $nodes = extractJsonLdByType((string) $this->get('/news/json-ld-uncategorised')->assertOk()->getContent());

    $names = array_column($nodes['BreadcrumbList']['itemListElement'], 'name');

    expect($names)->toBe(['首页', '资讯中心', '无分类资讯']);
});

/**
 * 正文含 </script> 字面量时不会提前闭合 script 标签
 *
 * JSON_HEX_TAG 保证 < > 被转义，否则后续正文会被浏览器当 HTML 执行。
 */
it('JSON-LD 转义尖括号防止提前闭合', function () {
    SiteCase::factory()->create([
        'slug'            => 'json-ld-xss',
        'seo_title'       => '标题里有 </script><img src=x onerror=alert(1)>',
        'seo_description' => '描述正常',
        'published_at'    => now()->subDay(),
    ]);

    $html = (string) $this->get('/cases/json-ld-xss')->assertOk()->getContent();

    preg_match('#<script type="application/ld\+json">(.*?)</script>#s', $html, $matches);
    $jsonLd = $matches[1] ?? '';

    // 转义后仍是合法 JSON-LD：解出来的标题一字不少，但 HTML 层看不到尖括号
    expect($jsonLd)->not->toBe('')
        // 尖括号一个不剩，浏览器不可能在这里提前结束 script
        ->and($jsonLd)->not->toContain('<')
        ->and($jsonLd)->not->toContain('>')
        // 但语义无损：解出来的标题一字不少
        ->and(json_decode($jsonLd, true)['headline'] ?? '')->toContain('</script>');
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

/**
 * 页面级 seo_og_image 进 og:image（#20）
 *
 * 此前 buildSeo() 只在 method_exists($record, 'ogImageUrl') 时取封面，
 * 而 SitePage 不是 media-library 模型没有该方法，于是后台「SEO」Tab 里填的
 * 「社交分享图 URL」从来没进过 og:image——填了等于没填。
 */
it('页面级 seo_og_image 进 og:image', function () {
    $settings                   = app(SiteSettings::class);
    $settings->og_default_image = 'https://cdn.test/global-default.jpg';
    $settings->save();

    SitePage::factory()->create([
        'slug'         => 'og-image-page',
        'seo_og_image' => 'https://cdn.test/page-specific.jpg',
    ]);

    $html = (string) $this->get('/og-image-page')->assertOk()->getContent();

    expect($html)->toContain('https://cdn.test/page-specific.jpg')
        // 页面级设置优先于全局默认
        ->and($html)->not->toContain('global-default.jpg');
});

/**
 * 页面未填 seo_og_image 时回退全局默认（#20）
 */
it('页面未填 og 图时回退全局默认', function () {
    $settings                   = app(SiteSettings::class);
    $settings->og_default_image = 'https://cdn.test/global-default.jpg';
    $settings->save();

    SitePage::factory()->create(['slug' => 'no-og-page', 'seo_og_image' => null]);

    $html = (string) $this->get('/no-og-page')->assertOk()->getContent();

    expect($html)->toContain('global-default.jpg');
});

/**
 * media-library 模型的回退链不受 #20 改动影响
 *
 * seo_og_image 只在 site_pages 上（D-10-16 起页面无封面图，靠这一列承载分享图）；
 * 案例 / 方案 / 产品走 HasCoverImage::ogImageUrl()。无封面时应回退全局默认，
 * 这条确认新加的 seo_og_image 分支没把 media-library 那条路截断。
 */
it('无封面案例回退全局默认 og 图', function () {
    $settings                   = app(SiteSettings::class);
    $settings->og_default_image = 'https://cdn.test/global-default.jpg';
    $settings->save();

    SiteCase::factory()->create([
        'slug'         => 'no-cover-case',
        'published_at' => now()->subDay(),
    ]);

    $html = (string) $this->get('/cases/no-cover-case')->assertOk()->getContent();

    expect($html)->toContain('global-default.jpg');
});

/**
 * 资讯归档页 canonical 自指，不指向 /news（#20 复核项）
 *
 * 归档页有独立内容（某个月的文章列表），canonical 指向 /news 等于告诉
 * 搜索引擎这些月份页都是资讯首页的副本，深层内容不会被索引。
 */
it('资讯归档页 canonical 自指', function () {
    NewsArticle::factory()->create([
        'slug'         => 'archive-canonical-article',
        'published_at' => now()->subDays(2),
    ]);

    $month = now()->subDays(2)->format('m');
    $year  = now()->subDays(2)->format('Y');

    $html = (string) $this->get("/news/archive/{$year}/{$month}")->assertOk()->getContent();

    preg_match('/<link rel="canonical" href="([^"]*)"/', $html, $matches);
    $canonical = $matches[1] ?? '';

    expect($canonical)->toContain("/news/archive/{$year}/{$month}")
        ->and($canonical)->not->toEndWith('/news');
});

/**
 * 资讯列表页的 category 参数保留在 canonical 里（#20 复核项）
 *
 * category 真正区分内容（不同分类是不同的文章集合），剥掉它会让所有分类页
 * 的 canonical 都指向 /news，分类页从此不被索引。
 */
it('canonical 保留 category 参数', function () {
    $html = (string) $this->get('/news?category=smart-home')->assertOk()->getContent();

    preg_match('/<link rel="canonical" href="([^"]*)"/', $html, $matches);
    $canonical = $matches[1] ?? '';

    expect($canonical)->toContain('category=smart-home');
});

/**
 * category 不在 canonical 剥离清单里（结构约束，防日后误加）
 */
it('category 不在 canonical 剥离清单中', function () {
    $ignored = (array) config('filamentboot-site.seo.canonical_ignored_params', []);

    expect($ignored)->not->toContain('category')
        ->and($ignored)->not->toContain('page')
        // 追踪参数必须在清单里
        ->and($ignored)->toContain('utm_source')
        ->and($ignored)->toContain('gclid');
});

/**
 * 后台上传的媒体字段归一成完整 URL
 *
 * logo / wechat_qrcode / og_default_image 有两种取值来源：后台 FileUpload 存的是
 * 相对磁盘根的路径，而直接写库配置时填的是完整 URL。视图此前不加区分地塞进 src，
 * 拿到相对路径时浏览器按当前页面路径解析——详情页上的 LOGO 请求打到
 * /cases/xxx.png，404。只在有人从后台传过图的站点上出现，是典型的静默失效。
 */
it('后台上传的相对路径归一成完整 URL', function () {
    $settings                   = app(SiteSettings::class);
    $settings->logo             = 'uploaded-logo.png';
    $settings->wechat_qrcode    = 'uploaded-qr.png';
    $settings->og_default_image = 'uploaded-og.jpg';
    $settings->save();

    $html = (string) $this->get('/')->assertOk()->getContent();

    foreach (['uploaded-logo.png', 'uploaded-qr.png', 'uploaded-og.jpg'] as $file) {
        expect($html)->toContain(Storage::disk('public')->url($file));
    }

    // 相对路径不能原样出现在任何 src / content 属性里
    expect($html)->not->toContain('"uploaded-logo.png"')
        ->and($html)->not->toContain('"uploaded-qr.png"')
        ->and($html)->not->toContain('"uploaded-og.jpg"');
});

/**
 * 已经是完整 URL 的值原样输出，不被再套一层磁盘前缀
 */
it('完整 URL 的媒体字段原样输出', function () {
    $settings                = app(SiteSettings::class);
    $settings->wechat_qrcode = 'https://cdn.test/qr.png';
    $settings->save();

    $this->get('/')->assertOk()->assertSee('https://cdn.test/qr.png', escape: false);

    expect(app(SiteSettings::class)->wechatQrcodeUrl())->toBe('https://cdn.test/qr.png');
});

/*
|--------------------------------------------------------------------------
| 三期批次 9：全站 SEO 收口
|--------------------------------------------------------------------------
|
| 加了 337 个城市页之后，「无重复 title / 无空 description / 分页 canonical
| 不指错 / 正文在首字节 HTML 里」这四条**人肉查不过来**。它们全是静默失效：
| 页面照常 200，只是在搜索引擎眼里互相稀释。
|
| 与这批测试配套的是 `tools/site-audit/audit.py`——那个跑的是真实爬取（本地或
| 生产），能抓到测试造不出来的组合；这里锁的是**机制**，改坏了当场红。
| 两者不重复：测试保证代码对，体检保证数据也对。
*/

/**
 * ?page=1 的 canonical 归并回不带参数的地址
 *
 * page=1 与不带参数渲染的是同一批记录。分页器的「上一页 / 首页」链接会真的
 * 生成 ?page=1，所以它是站内可达地址；自指 canonical 等于主动声明
 * 「这是两个页面」，两边平分权重。page=2 起必须保留（上面那条测的就是它）。
 */
it('第一页的 canonical 不带 page 参数', function () {
    $html = (string) $this->get('/solutions?page=1')->assertOk()->getContent();

    preg_match('/<link rel="canonical" href="([^"]*)"/', $html, $matches);

    expect($matches[1] ?? '')->not->toContain('page=');
});

/**
 * 第 2 页起标题带页码
 *
 * 分页地址是自指 canonical 的，各自是独立的索引对象。共用一个 title 就是
 * 几十个同名页面——加在 buildListSeo() 而不是各调用点，是因为每个列表页
 * 都分页，漏一个不报错、只是静默重复。
 */
it('分页列表页标题带页码', function () {
    $first  = (string) $this->get('/solutions')->assertOk()->getContent();
    $second = (string) $this->get('/solutions?page=2')->assertOk()->getContent();

    preg_match('#<title>(.*?)</title>#s', $first, $a);
    preg_match('#<title>(.*?)</title>#s', $second, $b);

    expect($b[1] ?? '')->toContain('第 2 页')
        ->and($b[1] ?? '')->not->toBe($a[1] ?? '');
});

/**
 * 筛选后的案例列表标题跟着筛选条件走
 *
 * 5 种风格 × 7 种户型，每个组合都是一个自指 canonical 的地址。标题不跟着变
 * 就是几十个「装修案例」互相稀释——套餐列表（?layout=）早就是这个做法。
 */
it('筛选后的案例列表标题带上筛选条件', function () {
    SiteCase::factory()->create(['published_at' => now()->subDay(), 'style' => 'modern']);

    $plain    = (string) $this->get('/cases')->assertOk()->getContent();
    $filtered = (string) $this->get('/cases?style=modern')->assertOk()->getContent();

    preg_match('#<title>(.*?)</title>#s', $plain, $a);
    preg_match('#<title>(.*?)</title>#s', $filtered, $b);

    expect($b[1] ?? '')->not->toBe($a[1] ?? '')
        ->and($b[1] ?? '')->toContain('装修案例');
});

/**
 * 六类页面加城市页，title 互不重复且 description 都非空
 *
 * 一条用例同时锁两件事是有意的：它们的失败模式相同（模板里少插一个变量），
 * 而分成两条要把同一批记录造两遍。
 */
it('各类页面的 title 互不重复且 description 非空', function () {
    SiteCase::factory()->create(['published_at' => now()->subDay()]);
    SiteProduct::factory()->create(['published_at' => now()]);
    NewsArticle::factory()->create(['published_at' => now()->subDay()]);

    $province = SiteRegion::factory()->province()->create(['slug' => 'hubei-seo', 'name' => '湖北省']);
    $city     = SiteRegion::factory()->childOf($province)->create(['slug' => 'wuhan-seo', 'name' => '武汉市']);
    SiteCityPage::factory()->forRegion($city)->create();

    $paths = [
        '/', '/cases', '/solutions', '/packages', '/products', '/news',
        '/city', '/city/hubei-seo', '/city/hubei-seo/wuhan-seo',
    ];

    $titles = [];

    foreach ($paths as $path) {
        $html = (string) $this->get($path)->assertOk()->getContent();

        preg_match('#<title>(.*?)</title>#s', $html, $t);
        preg_match('/<meta name="description" content="([^"]*)"/', $html, $d);

        expect(trim($d[1] ?? ''))->not->toBe('', "「{$path}」的 meta description 是空的");

        $title = trim($t[1] ?? '');
        expect($titles)->not->toHaveKey($title, "「{$path}」与「".($titles[$title] ?? '').'」标题相同');

        $titles[$title] = $path;
    }
});

/**
 * 正文在首字节 HTML 里
 *
 * AI 抓取器多数不执行 JS，靠 JS 渲染的内容对 GEO 等于不存在。前台本来就是
 * 服务端渲染，这条锁的是**没被破坏**——哪天有人把正文塞进 Livewire 组件或
 * Alpine 的 x-text，页面看起来一模一样，机器那边直接归零。
 */
it('正文出现在首字节 HTML 里', function () {
    $article = NewsArticle::factory()->create([
        'published_at' => now()->subDay(),
        'content_zh'   => '<p>无主灯首先是一道算术题，面积乘照度再除以灯具效率。</p>',
    ]);

    $html = (string) $this->get('/news/'.$article->slug)->assertOk()->getContent();

    expect($html)->toContain('面积乘照度再除以灯具效率');
});

/**
 * 每页恰好一个 h1
 *
 * 首页的轮播三张全在 DOM 里（x-show 只管显示），每张一个 h1 就是一页三个。
 * 爬虫读的是整个 DOM，不是当前可见的那一张——静态截图上完全看不出来。
 */
it('首页只有一个 h1', function () {
    SiteBanner::factory()->count(3)->create(['is_enabled' => true, 'position' => 'home_top']);

    $html = (string) $this->get('/')->assertOk()->getContent();

    expect(substr_count($html, '<h1'))->toBe(1);
});

/**
 * Organization 的 name 是法定公司名，不是 SEO 标题
 *
 * 此前取的是 defaultTitle()（「XX智能家居 - 全屋智能方案设计与施工」这种），
 * 于是对外声明的实体名与页脚、ICP 备案、工商登记三处全对不上。搜索引擎与 AI
 * 正是靠实体名消歧决定要不要引用——几处打架就直接跳过。
 */
it('Organization 用法定公司名做实体名', function () {
    $settings                          = app(SiteSettings::class);
    $settings->company_name_zh         = '某某科技有限公司';
    $settings->seo_default_title_zh    = '某某智能家居 - 全屋智能方案';
    $settings->save();

    $organization = extractJsonLdByType((string) $this->get('/')->assertOk()->getContent())['Organization'] ?? [];

    expect($organization['name'] ?? '')->toBe('某某科技有限公司')
        ->and($organization['alternateName'] ?? '')->toBe('某某智能家居 - 全屋智能方案');
});

/**
 * 实体一致性：页脚渲染的公司名与 Organization 的 name 逐字相同
 *
 * 两处取值来源不同（页脚读 $siteSettings、JSON-LD 走控制器），改一处漏一处
 * 不会报错。E2 第 4 条要的就是这个「逐字一致」。
 */
it('页脚公司名与 Organization 实体名逐字一致', function () {
    $settings                  = app(SiteSettings::class);
    $settings->company_name_zh = '某某科技有限公司';
    $settings->save();

    $html         = (string) $this->get('/')->assertOk()->getContent();
    $organization = extractJsonLdByType($html)['Organization'] ?? [];

    expect($organization['name'] ?? '')->toBe('某某科技有限公司')
        ->and($html)->toContain('某某科技有限公司');
});

/**
 * 资讯详情页可见署名与更新时间（EEAT）
 *
 * author 与 dateModified 早就在 Article 节点里了，但页面上看不到——
 * 百度 2026 起的 EEAT 评级看的是页面本身，只写进 JSON-LD 不算数。
 */
it('资讯详情页渲染署名与更新时间', function () {
    $settings                  = app(SiteSettings::class);
    $settings->company_name_zh = '某某科技有限公司';
    $settings->save();

    $article = NewsArticle::factory()->create(['published_at' => now()->subMonth()]);
    $article->forceFill(['updated_at' => now()])->saveQuietly();

    $html = (string) $this->get('/news/'.$article->slug)->assertOk()->getContent();

    expect($html)->toContain('某某科技有限公司')
        ->and($html)->toContain('更新于');
});

/**
 * 同一天保存过的内容不显示「更新于」
 *
 * updated_at 会被任何一次保存推到当天——把它当「更新过」是虚假的新鲜度信号，
 * 而新鲜度正是搜索引擎的排序输入之一。只在**跨天**时才算真的更新过。
 */
it('发布当天保存不产生更新时间', function () {
    $article = NewsArticle::factory()->create(['published_at' => now()->startOfDay()]);
    $article->forceFill(['updated_at' => now()->startOfDay()->addHours(6)])->saveQuietly();

    $html = (string) $this->get('/news/'.$article->slug)->assertOk()->getContent();

    expect($html)->not->toContain('更新于');
});
