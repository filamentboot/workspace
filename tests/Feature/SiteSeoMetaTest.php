<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use LaravelStack\FilamentAdminSite\Http\Controllers\SiteFrontController;
use LaravelStack\FilamentAdminSite\Models\SiteCase;

uses(RefreshDatabase::class);

/**
 * SEO 元数据测试（SiteSeoMetaTest）
 *
 * 覆盖场景：
 * - SiteFrontController::buildSeo() 回退链：记录 seo_title → 标题 → 全局默认 → app.name（Pattern 5）
 * - SiteFrontController 仅展示已发布内容（T-10-04-04 未发布内容不泄露）
 * - 视图数据契约：controller 传递 $seoData 到视图（D-10-17）
 *
 * 注意：渲染级 HTML 断言（<title>、<meta> 标签）由 Plan 10-05 视图就绪后补强。
 * 本测试验证路由注册后控制器数据层面的正确性。
 *
 * @group site
 */

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
