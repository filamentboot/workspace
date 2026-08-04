<?php

use Filamentboot\FilamentbootSite\Cms\Models\SitePage;
use Filamentboot\FilamentbootSite\Modules\Corporate\Cases\Models\SiteCase;
use Filamentboot\FilamentbootSite\Modules\Corporate\Products\Models\SiteProduct;
use Filamentboot\FilamentbootSite\Modules\News\Models\NewsArticle;
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
        'is_published' => true,
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
        'is_published' => true,
    ]);

    $schema = extractJsonLd((string) $this->get('/products/json-ld-no-price')->assertOk()->getContent());

    expect($schema)->not->toBeNull()
        ->and($schema['@type'])->toBe('Product')
        ->and($schema)->not->toHaveKey('offers');
});

/**
 * 列表页不输出 JSON-LD
 *
 * 结构化数据只在有具体实体的详情页才有意义。首页是例外——它承载
 * Organization（见下一例），列表页仍然什么都不输出。
 */
it('列表页不输出 JSON-LD', function () {
    foreach (['/cases', '/products', '/solutions', '/news'] as $path) {
        $html = (string) $this->get($path)->assertOk()->getContent();

        expect($html)->not->toContain('application/ld+json');
    }
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
        'is_published' => true,
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
