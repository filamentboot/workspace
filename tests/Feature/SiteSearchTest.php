<?php

use Filamentboot\FilamentbootSite\Cms\Enums\PageStatus;
use Filamentboot\FilamentbootSite\Cms\Models\SitePage;
use Filamentboot\FilamentbootSite\Cms\Services\SiteSearch;
use Filamentboot\FilamentbootSite\Modules\Corporate\Cases\Models\SiteCase;
use Filamentboot\FilamentbootSite\Modules\Corporate\Products\Models\SiteProduct;
use Filamentboot\FilamentbootSite\Modules\Corporate\Solutions\Models\SiteSolution;
use Filamentboot\FilamentbootSite\Modules\News\Models\NewsArticle;
use Filamentboot\FilamentbootSite\Settings\SiteSettings;
use Filamentboot\FilamentbootSite\SiteServiceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * 站内搜索（跨模块）
 *
 * 覆盖三层：
 *   1. 取数：五类内容都能命中、只出已发布、LIKE 通配符被转义、分组与「还有更多」
 *   2. 缓存与索引边界：搜索页不起 session（可被共享缓存）但必须 noindex，
 *      否则无限的关键词 URL 空间会被收录成成千上万低价值页面
 *   3. 前台渲染：两套主题各一份结果页版式
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
        (new ReflectionMethod(SiteServiceProvider::class, $method))->invoke($provider);
    }

    app('router')->getRoutes()->refreshNameLookups();
    app('router')->getRoutes()->refreshActionLookups();
});

/**
 * 切换前台主题并重建视图命名空间
 *
 * 各测试文件各写一份：Pest 的函数声明是全局的，重名会在两文件同时加载时 fatal，
 * 单跑本文件时又拿不到别处定义的那个（同 SiteRelatedContentTest 的注释）。
 */
function switchThemeForSearch(string $theme): void
{
    $settings               = app(SiteSettings::class);
    $settings->active_theme = $theme;
    app()->instance(SiteSettings::class, $settings);

    (new ReflectionMethod(SiteServiceProvider::class, 'registerThemeViews'))
        ->invoke(new SiteServiceProvider(app()));

    app('view')->flushFinderCache();
}

/**
 * 取出所有分组里的命中标题
 *
 * @param  list<array{key: string, label: string, hasMore: bool, hits: list<array{title: string, excerpt: string, url: string}>}>  $groups
 * @return list<string>
 */
function searchTitles(array $groups): array
{
    $titles = [];

    foreach ($groups as $group) {
        foreach ($group['hits'] as $hit) {
            $titles[] = $hit['title'];
        }
    }

    return $titles;
}

// ---------------------------------------------------------------------------
// 取数
// ---------------------------------------------------------------------------

it('五类内容都能被搜到', function () {
    SitePage::factory()->create(['title_zh' => '关于智能锁的页面', 'status' => PageStatus::PUBLISHED]);
    SiteCase::factory()->create(['title_zh' => '智能锁入户案例']);
    SiteSolution::factory()->create(['title_zh' => '智能锁方案']);
    SiteProduct::factory()->create(['title_zh' => '智能锁 Pro']);
    NewsArticle::factory()->create(['title_zh' => '智能锁选购指南']);

    $groups = app(SiteSearch::class)->search('智能锁');

    expect(array_column($groups, 'key'))
        ->toBe(['page', 'case', 'solution', 'product', 'news'])
        ->and(searchTitles($groups))->toHaveCount(5);
});

it('命中正文而不只是标题', function () {
    SiteCase::factory()->create([
        'title_zh'   => '三居室改造',
        'content_zh' => '<p>全屋铺了地暖并接入了中央空调联动</p>',
    ]);

    $groups = app(SiteSearch::class)->search('中央空调');

    expect(searchTitles($groups))->toBe(['三居室改造']);
});

it('产品可按品牌搜到', function () {
    SiteProduct::factory()->create(['title_zh' => '入墙式面板', 'brand' => '绿米 Aqara']);

    expect(searchTitles(app(SiteSearch::class)->search('Aqara')))->toBe(['入墙式面板']);
});

it('未发布内容搜不到', function () {
    SiteCase::factory()->draft()->create(['title_zh' => '草稿案例带关键词']);
    SiteProduct::factory()->unpublished()->create(['title_zh' => '下架产品带关键词']);
    SitePage::factory()->draft()->create(['title_zh' => '草稿页面带关键词']);
    NewsArticle::factory()->draft()->create(['title_zh' => '草稿资讯带关键词']);

    expect(app(SiteSearch::class)->search('关键词'))->toBe([]);
});

it('关键词为空时不查库直接返回空', function () {
    SiteCase::factory()->create(['title_zh' => '任意案例']);

    expect(app(SiteSearch::class)->search(''))->toBe([])
        ->and(app(SiteSearch::class)->search('   '))->toBe([]);
});

it('LIKE 通配符被转义而不是当通配符用', function () {
    SiteCase::factory()->create(['title_zh' => '预算 50% 以内的改造']);
    SiteCase::factory()->create(['title_zh' => '完全无关的另一条']);

    // 不转义的话 % 会匹配任意内容，两条都会被搜出来
    expect(searchTitles(app(SiteSearch::class)->search('50%')))
        ->toBe(['预算 50% 以内的改造']);
});

it('下划线也被转义', function () {
    SiteCase::factory()->create(['title_zh' => '型号 a_b 的案例']);
    SiteCase::factory()->create(['title_zh' => '型号 axb 的案例']);

    // 不转义时 a_b 会匹配 axb
    expect(searchTitles(app(SiteSearch::class)->search('a_b')))
        ->toBe(['型号 a_b 的案例']);
});

it('转义字符本身出现在关键词里也不破坏查询', function () {
    SiteCase::factory()->create(['title_zh' => '促销!! 特价案例']);

    expect(searchTitles(app(SiteSearch::class)->search('促销!!')))
        ->toBe(['促销!! 特价案例']);
});

it('每类内容最多返回 PER_GROUP 条并标记还有更多', function () {
    SiteCase::factory()->count(SiteSearch::PER_GROUP + 3)->create(['title_zh' => '智能家居案例']);

    $groups = app(SiteSearch::class)->search('智能家居');

    expect($groups)->toHaveCount(1)
        ->and($groups[0]['hits'])->toHaveCount(SiteSearch::PER_GROUP)
        ->and($groups[0]['hasMore'])->toBeTrue();
});

it('刚好不超过上限时不标记还有更多', function () {
    SiteCase::factory()->count(SiteSearch::PER_GROUP)->create(['title_zh' => '智能家居案例']);

    expect(app(SiteSearch::class)->search('智能家居')[0]['hasMore'])->toBeFalse();
});

it('关键词超长被截断而不是报错', function () {
    $search = app(SiteSearch::class);

    expect(mb_strlen($search->normalize(str_repeat('长', 200))))
        ->toBe(SiteSearch::MAX_TERM_LENGTH);
});

it('归一化折叠空白与控制字符', function () {
    $search = app(SiteSearch::class);

    expect($search->normalize('  智能   家居  '))->toBe('智能 家居')
        ->and($search->normalize("智能\x00家居"))->toBe('智能家居');
});

it('摘要剥掉 HTML 标签', function () {
    SiteCase::factory()->create([
        'title_zh'       => '标签测试案例',
        'description_zh' => '',
        'content_zh'     => '<p>前面一段铺垫文字</p><script>alert(1)</script><p>这里提到了新风系统</p>',
    ]);

    $excerpt = app(SiteSearch::class)->search('新风')[0]['hits'][0]['excerpt'];

    expect($excerpt)->not->toContain('<p>')
        ->and($excerpt)->not->toContain('<script')
        ->and($excerpt)->toContain('新风系统');
});

it('命中位置靠后时摘要从命中附近截取', function () {
    SiteCase::factory()->create([
        'title_zh'       => '长文案例',
        'description_zh' => '',
        'content_zh'     => str_repeat('铺垫', 200).'这里才提到全屋净水',
    ]);

    $excerpt = app(SiteSearch::class)->search('全屋净水')[0]['hits'][0]['excerpt'];

    expect($excerpt)->toContain('全屋净水')
        ->and($excerpt)->toStartWith('…');
});

// ---------------------------------------------------------------------------
// 缓存与索引边界
// ---------------------------------------------------------------------------

it('搜索页 noindex：关键词 URL 空间无限，被收录会稀释整站权重', function () {
    $response = $this->get('/search?q=智能锁')->assertOk();

    expect($response->headers->get('X-Robots-Tag'))->toContain('noindex');
});

it('搜索页不起 session 且可被公共缓存', function () {
    $response = $this->get('/search?q=智能锁')->assertOk();

    expect($response->headers->getCookies())->toBe([])
        ->and($response->headers->get('Cache-Control'))->toContain('public');

    $html = (string) $response->getContent();

    // 表单是 GET 且不带 @csrf——加了就会起 session，整页缓存静默失效
    expect($html)->not->toContain('wire:snapshot')
        ->and($html)->not->toContain('name="_token"');
});

it('搜索页不输出自指 canonical', function () {
    $html = (string) $this->get('/search?q=智能锁')->assertOk()->getContent();

    expect($html)->not->toContain('rel="canonical"');
});

it('search 已列入保留 slug，不会被静态页路由吞掉', function () {
    expect(config('filamentboot-site.route.reserved_slugs'))->toContain('search');

    // 即便真有人建了 slug=search 的页面，/search 仍然是搜索页
    SitePage::factory()->create([
        'slug'     => 'search',
        'title_zh' => '这是一个页面不是搜索',
        'status'   => PageStatus::PUBLISHED,
    ]);

    $this->get('/search')->assertOk()->assertDontSee('这是一个页面不是搜索');
});

// ---------------------------------------------------------------------------
// 前台渲染（双主题）
// ---------------------------------------------------------------------------

it('搜索结果页在双主题下渲染命中', function (string $theme) {
    switchThemeForSearch($theme);

    SiteCase::factory()->create(['title_zh' => '洪山区三居室智能改造', 'slug' => 'search-hit-case']);

    $html = (string) $this->get('/search?q=智能改造')->assertOk()->getContent();

    expect($html)->toContain('洪山区三居室智能改造')
        ->and($html)->toContain('装修案例')
        ->and($html)->toContain('/cases/search-hit-case')
        // 关键词回显到输入框
        ->and($html)->toContain('value="智能改造"');
})->with(['decoration', 'tech-product']);

it('无结果时在双主题下给出询盘出口', function (string $theme) {
    switchThemeForSearch($theme);

    $html = (string) $this->get('/search?q=一定搜不到的词')->assertOk()->getContent();

    expect($html)->toContain('没有找到')
        // 空结果页不该是死路：登记过的来源标识，能归因
        ->and($html)->toContain('search-empty');
})->with(['decoration', 'tech-product']);

it('未带关键词时在双主题下只显示搜索框', function (string $theme) {
    switchThemeForSearch($theme);

    $html = (string) $this->get('/search')->assertOk()->getContent();

    expect($html)->toContain('输入关键词开始搜索')
        ->and($html)->toContain('name="q"');
})->with(['decoration', 'tech-product']);

it('两套主题的导航都有搜索入口', function (string $theme) {
    switchThemeForSearch($theme);

    $html = (string) $this->get('/')->assertOk()->getContent();

    expect($html)->toContain('aria-label="站内搜索"')
        ->and($html)->toContain('站内搜索');
})->with(['decoration', 'tech-product']);
