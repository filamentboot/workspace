<?php

use Filamentboot\FilamentbootSite\Cms\Models\SiteMenu;
use Filamentboot\FilamentbootSite\Cms\Models\SiteMenuItem;
use Filamentboot\FilamentbootSite\Cms\Models\SitePage;
use Filamentboot\FilamentbootSite\Cms\Services\MenuResolver;
use Filamentboot\FilamentbootSite\Cms\Themes\ThemeManifest;
use Filamentboot\FilamentbootSite\Modules\Corporate\Cases\Models\SiteCase;
use Filamentboot\FilamentbootSite\Modules\Corporate\Home\HomeSectionProvider;
use Filamentboot\FilamentbootSite\Modules\Corporate\Products\Models\SiteProduct;
use Filamentboot\FilamentbootSite\Modules\News\Models\NewsArticle;
use Filamentboot\FilamentbootSite\Modules\News\Models\NewsCategory;
use Filamentboot\FilamentbootSite\Settings\SiteSettings;
use Filamentboot\FilamentbootSite\SiteServiceProvider;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * 内容承载视图渲染测试（产品图集轮播 / 业主见证 / 首页见证轮播）
 *
 * 两套主题各自持有完整视图、刻意不共享，因此每个断言都要在两个主题下各跑一遍——
 * 只测默认主题的话，另一套主题缺个变量或写错 token 要等到手工切主题才暴露。
 *
 * 主题切换用反射调用 SiteServiceProvider::registerThemeViews()：
 * 该方法内部按 active_theme 重建命名空间 hint 列表，是主题生效的唯一入口。
 *
 * 前台路由同样要手工加载：routes/site.php 只在 plugins.is_enabled = true 时被
 * SiteServiceProvider::registerFrontend() 引入，而测试库在应用 boot 之后才有数据。
 *
 * @group site
 */
beforeEach(function () {
    // 这三件事在 SiteServiceProvider::boot() 里都在 is_enabled 门控之内：
    // Livewire 命名空间（布局里的询盘表单）、$siteSettings composer、前台路由
    $provider = new SiteServiceProvider(app());

    foreach (['shareSiteSettings'] as $method) {
        (new ReflectionMethod(SiteServiceProvider::class, $method))->invoke($provider);
    }

    require base_path('packages/filamentboot-site/routes/site.php');

    app('router')->getRoutes()->refreshNameLookups();
    app('router')->getRoutes()->refreshActionLookups();
});

/**
 * 一张真实的 1x1 PNG——媒体库转换会实际跑图形库，随便塞字节会抛 CouldNotLoadImage
 */
function tinyPngBytes(): string
{
    return base64_decode(
        'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='
    );
}

/**
 * 切换前台主题并重建视图命名空间
 */
function switchSiteTheme(string $theme): void
{
    $settings               = app(SiteSettings::class);
    $settings->active_theme = $theme;
    app()->instance(SiteSettings::class, $settings);

    $provider = new SiteServiceProvider(app());
    $method   = new ReflectionMethod(SiteServiceProvider::class, 'registerThemeViews');
    $method->invoke($provider);

    app('view')->flushFinderCache();
}

dataset('themes', ['decoration', 'tech-product']);

/**
 * 产品详情页渲染图集轮播：每张图都在 DOM 里，缩略图按钮可切换
 */
it('产品详情页渲染图集轮播', function (string $theme) {
    switchSiteTheme($theme);

    $product = SiteProduct::factory()->create([
        'slug'         => 'zhi-neng-mian-ban',
        'title_zh'     => '智能面板',
        'is_published' => true,
        'content_zh'   => '<p>支持米家与 HomeKit 双生态。</p>',
    ]);

    foreach (['a', 'b', 'c'] as $name) {
        $product->addMediaFromString(tinyPngBytes())
            ->usingFileName($name.'.png')
            ->toMediaCollection('gallery');
    }

    $this->get(route('site.products.show', $product->slug))
        ->assertOk()
        ->assertSee('x-data', false)
        ->assertSee('total: 3', false)
        ->assertSee('查看第 3 张产品图')
        ->assertSee('产品详情')
        ->assertSee('双生态');
})->with('themes');

/**
 * 无图集无封面时降级到占位组件，不出破图
 */
it('产品无任何图片时降级到占位组件', function (string $theme) {
    switchSiteTheme($theme);

    $product = SiteProduct::factory()->create([
        'slug'         => 'wu-tu-chan-pin',
        'title_zh'     => '无图产品',
        'is_published' => true,
    ]);

    $response = $this->get(route('site.products.show', $product->slug))
        ->assertOk()
        // 占位组件的无障碍标签，与导航里的「智能产品」字样区分开
        ->assertSee('智能产品（暂无图片）');

    // 不产生空 src 的破图，也不渲染轮播控件
    expect($response->content())
        ->not->toContain('<img src=""')
        ->not->toContain('查看第 1 张产品图');
})->with('themes');

/**
 * 案例详情页在配齐姓名与引言时渲染业主见证
 */
it('案例详情页渲染业主见证', function (string $theme) {
    switchSiteTheme($theme);

    $case = SiteCase::factory()->create([
        'slug'           => 'wan-ke-cheng-shi-zhi-guang',
        'title_zh'       => '万科城市之光全屋智能',
        'published_at'   => now(),
        'customer_name'  => '张先生',
        'customer_meta'  => '万科城市之光 · 入住 8 个月',
        'customer_quote' => '回家灯就亮了，这点比什么都实在。',
    ]);

    $this->get(route('site.cases.show', $case->slug))
        ->assertOk()
        ->assertSee('张先生')
        ->assertSee('入住 8 个月')
        ->assertSee('回家灯就亮了，这点比什么都实在。')
        // 无头像时用称呼首字生成占位圆标
        ->assertSee('>张<', false);
})->with('themes');

/**
 * 只填了姓名没填引言时不渲染见证卡片，避免空壳
 */
it('业主见证信息不全时不渲染卡片', function (string $theme) {
    switchSiteTheme($theme);

    $case = SiteCase::factory()->create([
        'slug'           => 'zhi-you-xing-ming',
        'title_zh'       => '只填了姓名的案例',
        'published_at'   => now(),
        'customer_name'  => '李女士',
        'customer_quote' => null,
    ]);

    $this->get(route('site.cases.show', $case->slug))
        ->assertOk()
        ->assertDontSee('李女士');
})->with('themes');

/**
 * 首页见证轮播只收录填齐了姓名与引言的案例
 */
it('首页见证轮播过滤未配置见证的案例', function (string $theme) {
    switchSiteTheme($theme);

    SiteCase::factory()->create([
        'slug'           => 'you-jian-zheng',
        'title_zh'       => '有见证的案例',
        'published_at'   => now(),
        'customer_name'  => '王先生',
        'customer_quote' => '施工干净，收尾没让我操心。',
    ]);

    SiteCase::factory()->create([
        'slug'          => 'wu-jian-zheng',
        'title_zh'      => '没有见证的案例',
        'published_at'  => now(),
        'customer_name' => null,
    ]);

    $this->get(route('site.home'))
        ->assertOk()
        ->assertSee('业主说')
        ->assertSee('王先生')
        ->assertSee('施工干净，收尾没让我操心。');
})->with('themes');

/**
 * 资讯三个页面在两套主题下都能渲染
 *
 * 资讯是本期唯一整套新建的前台模块，列表/详情/归档任一主题缺个变量就是 500。
 */
it('资讯列表详情归档三页在两套主题下均可渲染', function (string $theme) {
    switchSiteTheme($theme);

    $category = NewsCategory::factory()->create([
        'name_zh' => '选型指南',
        'slug'    => 'xuan-xing-zhi-nan',
    ]);

    $published = now()->subMonthNoOverflow()->startOfMonth()->addDays(5);

    $article = NewsArticle::factory()->create([
        'title_zh'     => '网关到底要不要买双模',
        'slug'         => 'wang-guan-shuang-mo',
        'excerpt_zh'   => '协议选错，后面每加一个设备都是一次妥协。',
        'content_zh'   => '<p>结论先给：预算够就上双模。</p>',
        'category_id'  => $category->id,
        'published_at' => $published,
    ]);

    $article->addMediaFromString(tinyPngBytes())
        ->usingFileName('cover.png')
        ->toMediaCollection('cover');

    // 列表页：卡片 + 分类筛选 + 归档侧栏
    $this->get(route('site.news.index'))
        ->assertOk()
        ->assertSee('网关到底要不要买双模')
        ->assertSee('协议选错，后面每加一个设备都是一次妥协。')
        ->assertSee('选型指南')
        ->assertSee('按月归档');

    // 详情页：正文经 purifier 过滤后输出
    $this->get(route('site.news.show', $article->slug))
        ->assertOk()
        ->assertSee('网关到底要不要买双模')
        ->assertSee('预算够就上双模');

    // 归档页
    $this->get(route('site.news.archive', [
        'year'  => $published->format('Y'),
        'month' => $published->format('m'),
    ]))
        ->assertOk()
        ->assertSee('资讯归档')
        ->assertSee('网关到底要不要买双模');
})->with('themes');

/**
 * 无封面的资讯卡片降级到占位组件，不出破图
 */
it('资讯无封面时降级到占位组件', function (string $theme) {
    switchSiteTheme($theme);

    NewsArticle::factory()->create([
        'title_zh'     => '没有配图的文章',
        'slug'         => 'wu-pei-tu',
        'published_at' => now()->subDay(),
    ]);

    $response = $this->get(route('site.news.index'))
        ->assertOk()
        ->assertSee('智能家居资讯（暂无图片）');

    expect($response->content())->not->toContain('<img src=""');
})->with('themes');

/**
 * 站上一条见证都没有时整段不渲染，不留空标题
 */
it('无任何见证时首页不渲染见证段', function (string $theme) {
    switchSiteTheme($theme);

    SiteCase::factory()->create([
        'slug'          => 'pu-tong-an-li',
        'title_zh'      => '普通案例',
        'published_at'  => now(),
        'customer_name' => null,
    ]);

    $this->get(route('site.home'))
        ->assertOk()
        ->assertDontSee('业主说');
})->with('themes');

/**
 * 详情页正文的结构必须活着到 HTML（两套主题各测一遍）
 *
 * 视图此前调的是 app('purifier')->clean($content)，走 default 画像，
 * 实测把 h2/h3、引用、代码块、删除线、上下标全剥掉，表格更是整张塌成散段落。
 * 现在统一走 Support\RichText。这条测的是「视图确实换过去了」——
 * RichText 自身的白名单在 SiteRichTextTest 里单独钉。
 */
it('资讯详情页保留编辑器排的版式', function (string $theme) {
    switchSiteTheme($theme);

    $article = NewsArticle::factory()->create([
        'title_zh'     => '全屋智能选型对照',
        'slug'         => 'xuan-xing-dui-zhao',
        'published_at' => now()->subDay(),
        'content_zh'   => '<h2>协议对照</h2>'
            .'<table><tbody><tr><th><p>协议</p></th><td colspan="2"><p>Zigbee</p></td></tr></tbody></table>'
            .'<blockquote><p>预算够就上双模。</p></blockquote>'
            .'<pre><code>reset gateway</code></pre>'
            .'<p>面积 100m<sup>2</sup> 以上建议 <s>单模</s> 双模。</p>',
    ]);

    $html = $this->get(route('site.news.show', $article->slug))
        ->assertOk()
        ->content();

    foreach (['<h2>', '<table>', '<th', 'colspan="2"', '<blockquote>', '<pre>', '<code>', '<sup>', '<s>'] as $tag) {
        $this->assertStringContainsString($tag, $html, "{$theme} 主题的正文丢了 {$tag}");
    }
})->with('themes');

/**
 * 面包屑在两套主题下都渲染，且当前页不出链接（B3）
 *
 * 两套主题各持一份 breadcrumb.blade.php，只测默认主题的话另一套写错变量名
 * 要等手工切主题才暴露。当前页用 aria-current 而非 <a>：给读屏用户一个
 * 「你在这」的锚点，也避免一个指向自身的无意义链接。
 */
it('详情页在两套主题下都渲染面包屑', function (string $theme) {
    switchSiteTheme($theme);

    $case = SiteCase::factory()->create([
        'title_zh'     => '面包屑校验案例',
        'slug'         => 'breadcrumb-visible',
        'published_at' => now()->subDay(),
    ]);

    $html = $this->get(route('site.cases.show', $case->slug))->assertOk()->content();

    foreach (['aria-label="面包屑"', 'aria-current="page"', '装修案例', '面包屑校验案例'] as $needle) {
        $this->assertStringContainsString($needle, $html, "{$theme} 主题的面包屑缺了「{$needle}」");
    }
})->with('themes');

/**
 * 列表页不出面包屑
 *
 * 只有「首页 › 装修案例」两级，没有信息量，白占一行。
 */
it('列表页不渲染面包屑', function (string $theme) {
    switchSiteTheme($theme);

    $this->get(route('site.cases.index'))
        ->assertOk()
        ->assertDontSee('aria-label="面包屑"', false);
})->with('themes');

/**
 * 移动端底部三段式操作条（C1）
 *
 * 国内营销站的标准转化形态。此前移动端只有一个悬浮气泡，tel: 链接埋在页脚，
 * 等于把最短的转化路径藏起来。
 */
it('两套主题都渲染移动端三段式操作条', function (string $theme) {
    switchSiteTheme($theme);

    $settings                = app(SiteSettings::class);
    $settings->phone         = '027-8888 9999';
    $settings->wechat_qrcode = 'https://cdn.example.com/qr.png';
    $settings->save();

    $html = $this->get(route('site.home'))->assertOk()->content();

    foreach ([
        'aria-label="快捷联系"',
        // tel: 里必须是纯号码，带空格和横杠在部分安卓拨号盘上解析失败
        'href="tel:02788889999"',
        'https://cdn.example.com/qr.png',
        'data-contact-trigger="mobile-bar"',
        "\$store.contactPanel.show('mobile-bar')",
    ] as $needle) {
        $this->assertStringContainsString($needle, $html, "{$theme} 主题的操作条缺了「{$needle}」");
    }
})->with('themes');

/**
 * 缺数据的段落整段不渲染，不留死按钮
 */
it('未配置联系方式时操作条只剩留言段', function (string $theme) {
    switchSiteTheme($theme);

    $settings                = app(SiteSettings::class);
    $settings->phone         = '';
    $settings->wechat_qrcode = null;
    $settings->save();

    $html = $this->get(route('site.home'))->assertOk()->content();

    expect($html)->not->toContain('href="tel:')
        ->and($html)->not->toContain('打开微信咨询二维码')
        // 留言段不依赖任何设置，永远在
        ->and($html)->toContain('data-contact-trigger="mobile-bar"');
})->with('themes');

/**
 * 悬浮气泡在移动端让位给操作条
 *
 * 两个入口同屏是重复噪音，气泡还会压在操作条上。
 */
it('移动端隐藏悬浮气泡', function (string $theme) {
    switchSiteTheme($theme);

    $html = $this->get(route('site.home'))->assertOk()->content();

    // 气泡按钮本体带 hidden sm:inline-flex；滑入面板不受影响，两者共用同一 Store
    $this->assertStringContainsString('hidden sm:inline-flex', $html, "{$theme} 主题的悬浮气泡未在移动端隐藏");
    $this->assertStringContainsString('id="contact-panel"', $html, "{$theme} 主题的滑入面板不该被一起隐藏");
})->with('themes');

/**
 * 页面区块在两套主题下都真实渲染（#13）
 *
 * 区块视图各主题一份完整副本（§0.3 第 1 条），必须两套都跑：
 * 只测默认主题时，另一套缺一份视图会一路静默降级到「区块不显示」，
 * 而 BlockRenderer 的降级是有意设计，日志里也只是 warning。
 */
it('页面区块在双主题下渲染', function (string $theme) {
    switchSiteTheme($theme);

    SitePage::factory()->create([
        'slug'       => 'blocks-render-check',
        'title_zh'   => '区块渲染检查页',
        'content_zh' => '<p>这是原有富文本正文</p>',
        'blocks'     => [
            ['type' => 'hero', 'data' => ['title' => '区块首屏标题', 'subtitle' => '区块副标题']],
            ['type' => 'feature-grid', 'data' => [
                'title'   => '区块特性网格',
                'columns' => 3,
                'items'   => [['title' => '特性一', 'description' => '描述一']],
            ]],
            ['type' => 'faq', 'data' => [
                'title' => '区块常见问题',
                'items' => [['question' => '区块问题一', 'answer' => '区块答案一']],
            ]],
            ['type' => 'cta', 'data' => ['title' => '区块转化点', 'button_label' => '区块按钮', 'button_url' => '']],
        ],
    ]);

    $html = $this->get('/blocks-render-check')->assertOk()->content();

    foreach ([
        '区块首屏标题',
        '区块副标题',
        '区块特性网格',
        '特性一',
        '区块常见问题',
        '区块问题一',
        '区块转化点',
        '区块按钮',
    ] as $needle) {
        $this->assertStringContainsString($needle, $html, "{$theme} 主题未渲染区块内容「{$needle}」");
    }

    // 正文与区块并存，不是二选一
    $this->assertStringContainsString('这是原有富文本正文', $html, "{$theme} 主题丢了富文本正文");

    // CTA 空链接改为打开询盘面板，来源标识可归因
    $this->assertStringContainsString('data-contact-trigger="page-cta"', $html, "{$theme} 主题的 CTA 未接询盘面板");

    // FAQ 区块同时产出 FAQPage 结构化数据
    $this->assertStringContainsString('FAQPage', $html, "{$theme} 主题未输出 FAQPage 结构化数据");
})->with('themes');

/**
 * 区块里的富文本经白名单过滤，脚本不落地（#13 安全要点）
 */
it('区块富文本在双主题下都剥离脚本', function (string $theme) {
    switchSiteTheme($theme);

    SitePage::factory()->create([
        'slug'   => 'blocks-xss-check',
        'blocks' => [
            ['type' => 'rich-content', 'data' => [
                'title'   => '富文本区块标题',
                'content' => '<h3>三级标题</h3><p>正常段落</p><script>alert(1)</script><img src=x onerror=alert(2)>',
            ]],
        ],
    ]);

    $html = $this->get('/blocks-xss-check')->assertOk()->content();

    // 结构保住：h3 与段落都还在（RichText 的白名单与编辑器工具栏对齐）
    $this->assertStringContainsString('三级标题', $html, "{$theme} 主题丢了区块富文本的标题结构");
    $this->assertStringContainsString('正常段落', $html, "{$theme} 主题丢了区块富文本的段落");

    // 脚本与事件处理器一个不剩
    $this->assertStringNotContainsString('<script>alert', $html, "{$theme} 主题未剥离区块内的 script");
    $this->assertStringNotContainsString('onerror', $html, "{$theme} 主题未剥离区块内的 onerror");
})->with('themes');

/**
 * 未注册的区块 key 不把整页打成 500（#13 降级契约）
 */
it('未知区块在双主题下优雅降级', function (string $theme) {
    switchSiteTheme($theme);

    SitePage::factory()->create([
        'slug'   => 'blocks-unknown-check',
        'blocks' => [
            ['type' => 'no-such-block', 'data' => ['title' => '不该出现的内容']],
            ['type' => 'hero', 'data' => ['title' => '正常区块仍然渲染']],
        ],
    ]);

    $html = $this->get('/blocks-unknown-check')->assertOk()->content();

    $this->assertStringContainsString('正常区块仍然渲染', $html, "{$theme} 主题下未知区块拖累了正常区块");
    $this->assertStringNotContainsString('不该出现的内容', $html, "{$theme} 主题渲染了未注册区块的内容");
})->with('themes');

/**
 * 首页区块数据可通过替换 HomeSectionProvider 整体接管（#27）
 *
 * 抽出这个提供者的全部意义就是给宿主一个换内容源的接口——如果 bind 不生效，
 * 那这次抽取只是把代码挪了个地方。用一个空提供者做最直接的判据：
 * 换掉之后首页那批区块数据必须真的空掉。
 */
it('绑定自定义 HomeSectionProvider 后首页数据换源', function (string $theme) {
    switchSiteTheme($theme);

    SiteCase::factory()->create([
        'title_zh'       => '会被换掉的案例',
        'is_featured'    => true,
        'published_at'   => now()->subDay(),
        'customer_name'  => '张女士',
        'customer_quote' => '很满意',
    ]);

    // 未替换时首页看得到它
    $this->get('/')->assertOk()->assertSee('会被换掉的案例');

    app()->bind(HomeSectionProvider::class, fn (): HomeSectionProvider => new class extends HomeSectionProvider
    {
        /**
         * @return array<string, iterable<int, Model>>
         */
        public function sections(): array
        {
            return [
                'featuredCases'     => SiteCase::query()->whereRaw('1 = 0')->get(),
                'featuredSolutions' => SiteCase::query()->whereRaw('1 = 0')->get(),
                'featuredProducts'  => SiteCase::query()->whereRaw('1 = 0')->get(),
                'testimonials'      => SiteCase::query()->whereRaw('1 = 0')->get(),
            ];
        }
    });

    $this->get('/')->assertOk()->assertDontSee('会被换掉的案例');
})->with('themes');

/**
 * 二级导航在双主题下都渲染出下拉与子项（#28）
 *
 * 两套主题各写了一份下拉版式，只测默认主题的话另一套写错要等手工切主题才暴露。
 */
it('二级导航在双主题下渲染子项', function (string $theme) {
    switchSiteTheme($theme);

    $menu = SiteMenu::create(['key' => 'main', 'name' => '顶部导航']);

    $parent = SiteMenuItem::create([
        'menu_id'   => $menu->getKey(),
        'parent_id' => SiteMenuItem::defaultParentKey(),
        'type'      => 'anchor',
        'label'     => '关于我们',
        'target'    => '#about',
        'sort'      => 0,
    ]);

    SiteMenuItem::create([
        'menu_id'   => $menu->getKey(),
        'parent_id' => $parent->getKey(),
        'type'      => 'anchor',
        'label'     => '团队介绍',
        'target'    => '#team',
        'sort'      => 0,
    ]);

    MenuResolver::forget();
    ThemeManifest::flush();

    $html = $this->get('/')->assertOk()->getContent();

    expect($html)->toContain('关于我们')
        ->and($html)->toContain('团队介绍')
        // 下拉开合按钮，两套主题都用同一段 aria-label 文案
        ->and($html)->toContain('展开「关于我们」子菜单');
})->with('themes');

/**
 * landing 版式在双主题下都不出主导航（#28）
 *
 * 落地页的全部意义是把出路收敛到一个转化动作，导航栏每个栏目链接都是一条岔路。
 * 这条锁住「landing 不 extends layouts.app」这个决定不被后来的人顺手改回去。
 */
it('landing 版式在双主题下不渲染主导航', function (string $theme) {
    switchSiteTheme($theme);

    SitePage::factory()->create([
        'slug'     => 'landing-uat',
        'title_zh' => '落地页标题',
        'template' => 'landing',
        'blocks'   => [['type' => 'hero', 'data' => ['title' => '落地页区块标题']]],
    ]);

    $html = $this->get('/landing-uat')->assertOk()->getContent();

    expect($html)->toContain('落地页标题')
        // 区块照常渲染
        ->and($html)->toContain('落地页区块标题')
        // 没有主导航，也没有面包屑
        ->and($html)->not->toContain('aria-label="主导航"')
        // 唯一转化入口在头部
        ->and($html)->toContain('landing-header');
})->with('themes');

/**
 * 标准版式仍然带主导航（与上一条互为对照）
 */
it('标准版式仍渲染主导航', function (string $theme) {
    switchSiteTheme($theme);

    SitePage::factory()->create([
        'slug'     => 'normal-uat',
        'title_zh' => '标准页标题',
        'template' => 'default',
    ]);

    expect($this->get('/normal-uat')->assertOk()->getContent())
        ->toContain('aria-label="主导航"');
})->with('themes');

/**
 * 地图区块在双主题下渲染 iframe 与文字地址
 *
 * 文字地址不是地图的说明而是它的降级路径：广告拦截插件、企业网络策略与爬虫
 * 都会把 iframe 丢掉，那时候地址文字是唯一还看得到的信息，也是搜索引擎能读的那份。
 */
it('地图区块在双主题下渲染', function (string $theme) {
    switchSiteTheme($theme);

    config(['filamentboot-site.map.allowed_hosts' => ['map.baidu.com']]);

    SitePage::factory()->create([
        'slug'     => 'map-page',
        'title_zh' => '联系我们',
        'blocks'   => [[
            'type' => 'map',
            'data' => [
                'title'     => '公司位置',
                'embed_url' => 'https://map.baidu.com/?poi=demo',
                'address'   => '武汉市洪山区某路 1 号',
                'height'    => 560,
            ],
        ]],
    ]);

    $html = $this->get('/map-page')->assertOk()->getContent();

    expect($html)->toContain('公司位置')
        ->and($html)->toContain('武汉市洪山区某路 1 号')
        ->and($html)->toContain('https://map.baidu.com/?poi=demo')
        ->and($html)->toContain('height: 560px')
        // 地图瓦片与脚本有几百 KB，联系页要滚下去才看到，不该参与首屏竞争
        ->and($html)->toContain('loading="lazy"');
})->with('themes');

/**
 * 白名单外的嵌入地址：整个 iframe 不渲染，只留文字地址
 *
 * 不降级成空框（同 SafeUrl 的契约）。库里可能躺着白名单收紧之前存下的地址，
 * 所以渲染侧必须自己再过一遍，不能只靠保存时的校验。
 */
it('地图区块对白名单外地址只渲染文字地址', function (string $theme) {
    switchSiteTheme($theme);

    config(['filamentboot-site.map.allowed_hosts' => ['map.baidu.com']]);

    SitePage::factory()->create([
        'slug'     => 'map-blocked-page',
        'title_zh' => '联系我们',
        'blocks'   => [[
            'type' => 'map',
            'data' => [
                'embed_url' => 'https://evil.example/map',
                'address'   => '武汉市洪山区某路 2 号',
                'height'    => 420,
            ],
        ]],
    ]);

    $html = $this->get('/map-blocked-page')->assertOk()->getContent();

    expect($html)->toContain('武汉市洪山区某路 2 号')
        ->and($html)->not->toContain('evil.example')
        ->and($html)->not->toContain('<iframe');
})->with('themes');
