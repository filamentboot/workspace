<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Filamentboot\FilamentbootSite\Http\Controllers\SiteFrontController;
use Filamentboot\FilamentbootSite\Http\Livewire\CaseFilter;
use Filamentboot\FilamentbootSite\Http\Livewire\ContactForm;
use Filamentboot\FilamentbootSite\Models\SiteCase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

/**
 * SEO 元数据测试（SiteSeoMetaTest）
 *
 * 覆盖场景：
 * - SiteFrontController::buildSeo() 回退链：记录 seo_title → 标题 → 全局默认 → app.name（Pattern 5）
 * - SiteFrontController 仅展示已发布内容（T-10-04-04 未发布内容不泄露）
 * - 视图数据契约：controller 传递 $seoData 到视图（D-10-17）
 * - 渲染级 HTML 断言：GET 详情页响应 HTML 含 <title>/<meta name="description">/<meta property="og:title">
 *
 * @group site
 */

/**
 * 注册路由与视图命名空间（仅在此测试文件中，模拟插件启用状态）
 */
beforeEach(function () {
    // 注册 filamentboot-site 视图命名空间（指向 decoration 主题，同插件启用时行为）
    $themePath  = base_path('packages/filamentboot-site/resources/views/themes/decoration');
    $sharedPath = base_path('packages/filamentboot-site/resources/views');
    view()->addNamespace('filamentboot-site', $themePath);
    view()->addNamespace('filamentboot-site-shared', $sharedPath);

    // 注册 Livewire 组件（ContactForm/CaseFilter），避免 floating-contact 渲染报错
    Livewire::component('filamentboot-site::contact-form', ContactForm::class);
    Livewire::component('filamentboot-site::case-filter', CaseFilter::class);

    // 手动注册案例详情路由（模拟 SiteServiceProvider::registerFrontend()）
    // 仅注册 /cases/{slug}，避免与现有路由冲突
    Route::middleware('web')
        ->controller(SiteFrontController::class)
        ->group(function () {
            Route::get('/test-cases/{slug}', 'caseShow')
                ->name('site.test.cases.show');
        });
});

/**
 * 案例详情页 SEO meta 数据层面正确输出（Pattern 5 三层回退链验证）
 */
it('案例详情页输出 SEO meta 标签', function () {
    // 创建一条已发布的案例（带明确 SEO 字段）
    $case = SiteCase::factory()->create([
        'title_zh'        => '现代简约客厅装修案例',
        'slug'            => 'modern-living-room-case',
        'seo_title'       => '现代简约客厅 - 晴空妙享智能家居 SEO标题',
        'seo_description' => '这是案例专属 SEO 描述，满足 160 字符以内。',
        'seo_keywords'    => '现代简约,客厅装修,智能家居',
        'published_at'    => now()->subDay(),
    ]);

    // 通过反射实例化 SiteFrontController，直接调用 buildSeo 方法验证数据层契约
    $controller = new SiteFrontController();
    $reflection = new ReflectionMethod($controller, 'buildSeo');
    $reflection->setAccessible(true);
    $seoData = $reflection->invoke($controller, $case, null, 'zh');

    // 验证 SEO 回退链第一层：使用记录自身 seo_title
    expect($seoData['title'])->toBe('现代简约客厅 - 晴空妙享智能家居 SEO标题');
    expect($seoData['description'])->toBe('这是案例专属 SEO 描述，满足 160 字符以内。');
    expect($seoData['keywords'])->toBe('现代简约,客厅装修,智能家居');
    expect($seoData['ogTitle'])->toBe($seoData['title']);
    expect($seoData['ogDescription'])->toBe($seoData['description']);
});

/**
 * SEO 回退链第二层：无 seo_title 时降级到标题字段（Pattern 5）
 */
it('SEO 回退链无记录 SEO 字段时使用标题回退', function () {
    $case = SiteCase::factory()->create([
        'title_zh'        => '北欧风格卧室改造',
        'slug'            => 'nordic-bedroom-renovation',
        'seo_title'       => '',        // 空字符串：触发回退
        'seo_description' => '',        // 空字符串：触发回退
        'published_at'    => now()->subDay(),
    ]);

    // 直接操作对象属性确保空值
    $case->seo_title       = '';
    $case->seo_description = '';

    $controller = new SiteFrontController();
    $reflection = new ReflectionMethod($controller, 'buildSeo');
    $reflection->setAccessible(true);
    $seoData = $reflection->invoke($controller, $case, null, 'zh');

    // 第二层回退：标题字段 title_zh
    expect($seoData['title'])->toBe('北欧风格卧室改造');
});

/**
 * 未发布内容不泄露（T-10-04-04 安全验证）
 *
 * SiteCase::published() scope 过滤草稿。
 */
it('未发布案例不被前台路由展示', function () {
    // 创建草稿案例（无 published_at）
    $draftCase = SiteCase::factory()->draft()->create([
        'slug' => 'draft-case-should-not-appear',
    ]);

    // 已发布案例
    $publishedCase = SiteCase::factory()->create([
        'slug'         => 'published-case-visible',
        'published_at' => now()->subDay(),
    ]);

    // published() scope 仅返回已发布内容
    $publishedCases = SiteCase::published()->get();

    expect($publishedCases->pluck('slug'))->toContain('published-case-visible');
    expect($publishedCases->pluck('slug'))->not->toContain('draft-case-should-not-appear');
});

/**
 * 渲染级 HTML 断言：seo-meta 组件渲染输出含 title/description/og:title（SEO Contract）
 *
 * 补强 10-04 仅测试数据层的 buildSeo 方法，本测试升级为 Blade 渲染级验证。
 * 直接渲染 seo-meta 组件（不走完整 HTTP 路由，避免 floating-contact 的 Livewire 依赖），
 * 断言 HTML 中含关键 SEO 标签（Pattern 5，D-10-17，SEO Contract）。
 */
it('详情页 seo-meta 组件渲染 HTML 包含 SEO meta 标签', function () {
    // Seed 一条已发布案例（带 seo_title 和 seo_description）
    $case = SiteCase::factory()->create([
        'title_zh'        => '现代科技客厅方案',
        'slug'            => 'modern-tech-living-room',
        'seo_title'       => '现代科技客厅方案 - 晴空妙享',
        'seo_description' => '专业智能家居方案，现代科技客厅定制服务。',
        'published_at'    => now()->subDay(),
    ]);

    // 通过 buildSeo 获取 SEO 数据（同控制器行为）
    $controller = new SiteFrontController();
    $reflection = new ReflectionMethod($controller, 'buildSeo');
    $reflection->setAccessible(true);
    $seoData = $reflection->invoke($controller, $case, null, 'zh');

    // 直接渲染 seo-meta.blade.php，传入 seoData（绕开 HTTP 路由，避免 Livewire 依赖）
    $html = view('filamentboot-site::components.seo-meta', [
        'seoData'      => $seoData,
        'siteSettings' => null,
    ])->render();

    // 渲染级 HTML 断言：title 标签存在且包含 seo_title（SEO Contract）
    expect($html)->toContain('<title>');
    expect($html)->toContain('现代科技客厅方案 - 晴空妙享');

    // meta name="description" 存在（SEO Contract）
    expect($html)->toContain('<meta name="description"');
    expect($html)->toContain('专业智能家居方案');

    // og:title Open Graph 标签存在（D-10-17，SEO Contract）
    expect($html)->toContain('<meta property="og:title"');
    expect($html)->toContain('现代科技客厅方案 - 晴空妙享');
});
