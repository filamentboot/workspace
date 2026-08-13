<?php

use Filamentboot\FilamentbootSite\Modules\News\Models\NewsArticle;
use Filamentboot\FilamentbootSite\Modules\News\Models\NewsCategory;
use Filamentboot\FilamentbootSite\SiteServiceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * 资讯模块前台测试（SiteNewsTest）
 *
 * 覆盖场景：
 * - 列表页只出已发布文章，草稿与定时未到的文章不泄露（T-10-04-04）
 * - 分类筛选走查询参数，空分类不出筛选按钮
 * - 详情页 slug 404、相关阅读取同分类
 * - 归档页按年月切片，月份链接来自真实数据
 * - 站点地图收录文章、不收录归档页
 *
 * 路由挂 root 模式：与 SiteSeoMetaTest 一致，直接用 /news 这样的裸路径断言，
 * 免得每处都拼 prefix。
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
        (new ReflectionMethod($provider, $method))->invoke($provider);
    }

    app('router')->getRoutes()->refreshNameLookups();
    app('router')->getRoutes()->refreshActionLookups();
});

/**
 * 列表页展示已发布文章
 */
it('资讯列表页展示已发布文章', function () {
    NewsArticle::factory()->create([
        'title_zh'       => '全屋智能选型避坑指南',
        'slug'           => 'quan-wu-zhi-neng-xuan-xing',
        'description_zh' => '先定协议再买设备，顺序反了就得返工。',
        'published_at'   => now()->subDay(),
    ]);

    $this->get('/news')
        ->assertOk()
        ->assertSee('全屋智能选型避坑指南')
        ->assertSee('先定协议再买设备，顺序反了就得返工。');
});

/**
 * 草稿与定时未到的文章不出现在列表页（T-10-04-04）
 */
it('草稿与定时未到的文章不在列表页出现', function () {
    NewsArticle::factory()->draft()->create(['title_zh' => '这是一篇草稿文章']);
    NewsArticle::factory()->create([
        'title_zh'     => '这是一篇定时文章',
        'published_at' => now()->addWeek(),
    ]);
    NewsArticle::factory()->create([
        'title_zh'     => '这是一篇已发布文章',
        'published_at' => now()->subDay(),
    ]);

    $this->get('/news')
        ->assertOk()
        ->assertSee('这是一篇已发布文章')
        ->assertDontSee('这是一篇草稿文章')
        ->assertDontSee('这是一篇定时文章');
});

/**
 * 分类筛选只返回该分类下的文章
 */
it('分类筛选只返回该分类文章', function () {
    $selection = NewsCategory::factory()->create(['name_zh' => '选型指南', 'slug' => 'xuan-xing']);
    $site      = NewsCategory::factory()->create(['name_zh' => '施工现场', 'slug' => 'shi-gong']);

    NewsArticle::factory()->create([
        'title_zh'     => '开关面板怎么选',
        'category_id'  => $selection->id,
        'published_at' => now()->subDay(),
    ]);
    NewsArticle::factory()->create([
        'title_zh'     => '强弱电箱预留尺寸',
        'category_id'  => $site->id,
        'published_at' => now()->subDay(),
    ]);

    $this->get('/news?category=xuan-xing')
        ->assertOk()
        ->assertSee('开关面板怎么选')
        ->assertDontSee('强弱电箱预留尺寸')
        // 标题换成分类名，用户知道自己在筛选态
        ->assertSee('选型指南');
});

/**
 * 0 篇已发布文章的分类不出筛选按钮
 *
 * 出了按钮点进去只有空列表，等于给用户一个死胡同。
 */
it('无已发布文章的分类不出筛选按钮', function () {
    NewsCategory::factory()->create(['name_zh' => '空栏目啊', 'slug' => 'kong-lan-mu']);

    $hasArticles = NewsCategory::factory()->create(['name_zh' => '有文章的栏目', 'slug' => 'you-wen-zhang']);
    NewsArticle::factory()->create([
        'category_id'  => $hasArticles->id,
        'published_at' => now()->subDay(),
    ]);

    $this->get('/news')
        ->assertOk()
        ->assertSee('有文章的栏目')
        ->assertDontSee('空栏目啊');
});

/**
 * 详情页渲染正文，草稿与不存在的 slug 均 404
 */
it('资讯详情页渲染正文且草稿 404', function () {
    NewsArticle::factory()->create([
        'title_zh'     => '米家与 HomeKit 怎么共存',
        'slug'         => 'mijia-homekit',
        'content_zh'   => '<p>网关选双模的，别在协议上做单选题。</p>',
        'published_at' => now()->subDay(),
    ]);

    NewsArticle::factory()->draft()->create(['slug' => 'draft-article']);

    $this->get('/news/mijia-homekit')
        ->assertOk()
        ->assertSee('米家与 HomeKit 怎么共存')
        ->assertSee('别在协议上做单选题');

    $this->get('/news/draft-article')->assertNotFound();
    $this->get('/news/bu-cun-zai-de-slug')->assertNotFound();
});

/**
 * 相关阅读取同分类文章并排除自身
 */
it('详情页相关阅读取同分类且排除自身', function () {
    $category = NewsCategory::factory()->create(['slug' => 'xiang-guan']);
    $other    = NewsCategory::factory()->create(['slug' => 'wu-guan']);

    NewsArticle::factory()->create([
        'title_zh'     => '当前这篇文章',
        'slug'         => 'dang-qian-pian',
        'category_id'  => $category->id,
        'published_at' => now()->subDay(),
    ]);
    NewsArticle::factory()->create([
        'title_zh'     => '同分类的另一篇',
        'category_id'  => $category->id,
        'published_at' => now()->subDays(2),
    ]);
    NewsArticle::factory()->create([
        'title_zh'     => '别的分类的文章',
        'category_id'  => $other->id,
        'published_at' => now()->subDays(3),
    ]);

    $html = (string) $this->get('/news/dang-qian-pian')->assertOk()->getContent();

    expect($html)->toContain('相关阅读')
        ->and($html)->toContain('同分类的另一篇')
        ->and($html)->not->toContain('别的分类的文章')
        // 当前文章的标题只应出现在 h1 与 SEO meta 里，不该再进相关阅读卡片
        ->and(substr_count($html, '阅读全文：当前这篇文章'))->toBe(0);
});

/**
 * 归档页只返回指定年月的文章
 */
it('归档页按年月切片', function () {
    $thisMonth = now()->startOfMonth()->addDays(2);
    $lastMonth = now()->subMonthNoOverflow()->startOfMonth()->addDays(2);

    NewsArticle::factory()->create([
        'title_zh'     => '本月发布的文章',
        'published_at' => $thisMonth,
    ]);
    NewsArticle::factory()->create([
        'title_zh'     => '上月发布的文章',
        'published_at' => $lastMonth,
    ]);

    $this->get('/news/archive/'.$lastMonth->format('Y/m'))
        ->assertOk()
        ->assertSee('上月发布的文章')
        ->assertDontSee('本月发布的文章')
        ->assertSee($lastMonth->format('Y').' 年 '.(int) $lastMonth->format('m').' 月');
});

/**
 * 非法年月不匹配归档路由
 *
 * 路由 where 约束住 4 位年与 01-12 月，13 月只能落到 404，
 * 不能被 /news/{slug} 或其它路由接住。
 */
it('非法月份不匹配归档路由', function () {
    $this->get('/news/archive/2026/13')->assertNotFound();
    $this->get('/news/archive/26/08')->assertNotFound();
});

/**
 * 列表页归档侧栏的月份链接来自真实数据
 */
it('列表页归档侧栏列出有文章的月份', function () {
    $lastMonth = now()->subMonthNoOverflow()->startOfMonth()->addDays(2);

    NewsArticle::factory()->create(['published_at' => $lastMonth]);

    $this->get('/news')
        ->assertOk()
        ->assertSee('按月归档')
        ->assertSee('/news/archive/'.$lastMonth->format('Y/m'), escape: false);
});

/**
 * 站点地图收录已发布文章与资讯列表页，不收录草稿与归档页
 */
it('站点地图收录资讯文章', function () {
    NewsArticle::factory()->create([
        'slug'         => 'sitemap-shou-lu',
        'published_at' => now()->subDay(),
    ]);
    NewsArticle::factory()->draft()->create(['slug' => 'sitemap-cao-gao']);

    // 三期批次 4 起 /sitemap.xml 是**索引**，内容条目在 content 分片里
    $xml = (string) $this->get('/sitemap-content.xml')->assertOk()->getContent();

    expect($xml)->toContain('/news/sitemap-shou-lu')
        ->and($xml)->toContain('<loc>'.route('site.news.index').'</loc>')
        ->and($xml)->not->toContain('sitemap-cao-gao')
        ->and($xml)->not->toContain('/news/archive/');
});
