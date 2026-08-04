<?php

use Filamentboot\FilamentbootSite\Cms\Services\RelatedContent;
use Filamentboot\FilamentbootSite\Models\SiteTag;
use Filamentboot\FilamentbootSite\Modules\Corporate\Cases\Enums\CaseStyle;
use Filamentboot\FilamentbootSite\Modules\Corporate\Cases\Enums\HouseType;
use Filamentboot\FilamentbootSite\Modules\Corporate\Cases\Models\SiteCase;
use Filamentboot\FilamentbootSite\Modules\Corporate\Products\Models\SiteProduct;
use Filamentboot\FilamentbootSite\Modules\Corporate\Solutions\Models\SiteSolution;
use Filamentboot\FilamentbootSite\Settings\SiteSettings;
use Filamentboot\FilamentbootSite\SiteServiceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * 相关内容推荐（详情页底部）
 *
 * 覆盖两层：
 *   1. Cms\Services\RelatedContent 的取数语义——亲和优先、最新补齐、排除自身、只出已发布
 *   2. 案例 / 方案 / 产品三类详情页真的把这块渲染出来，**两套主题各跑一遍**
 *
 * 资讯详情页**不**走这个服务：它的「相关阅读」是严格同分类、不补齐（护栏在
 * SiteNewsTest 的「详情页相关阅读取同分类且排除自身」）。两种取舍不同是有意的，
 * 理由见 SiteFrontController::newsShow() 的注释。
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
 * 不复用 SiteContentRenderTest 里的同名函数：Pest 的测试文件是普通 PHP 文件，
 * 函数声明是全局的——重名会在两个文件同时加载时直接 fatal，而单跑本文件时
 * 那个函数又不存在。跨文件共享辅助函数只能各写一份或搬进 Pest.php。
 */
function switchThemeForRelated(string $theme): void
{
    $settings               = app(SiteSettings::class);
    $settings->active_theme = $theme;
    app()->instance(SiteSettings::class, $settings);

    (new ReflectionMethod(SiteServiceProvider::class, 'registerThemeViews'))
        ->invoke(new SiteServiceProvider(app()));

    app('view')->flushFinderCache();
}

// ---------------------------------------------------------------------------
// 取数语义
// ---------------------------------------------------------------------------

it('同风格的案例优先进入推荐', function () {
    $record = SiteCase::factory()->create([
        'style'      => CaseStyle::cases()[0]->value,
        'house_type' => HouseType::cases()[0]->value,
    ]);

    // 同风格但发布时间更早：若只按时间取最新，它会被后面三条挤掉
    $sameStyle = SiteCase::factory()->create([
        'style'        => CaseStyle::cases()[0]->value,
        'house_type'   => HouseType::cases()[1]->value,
        'published_at' => now()->subYear(),
    ]);

    SiteCase::factory()->count(5)->create([
        'style'      => CaseStyle::cases()[1]->value,
        'house_type' => HouseType::cases()[1]->value,
    ]);

    $related = app(RelatedContent::class)->for(
        SiteCase::published()->latest('published_at'),
        $record,
        ['style' => $record->style, 'house_type' => $record->house_type]
    );

    expect($related->modelKeys())->toContain($sameStyle->getKey());
});

it('亲和维度不足时用最新内容补齐到上限', function () {
    $record = SiteCase::factory()->create(['style' => CaseStyle::cases()[0]->value]);

    // 全站除自己外只有 5 条，且一条都不同风格
    SiteCase::factory()->count(5)->create(['style' => CaseStyle::cases()[1]->value]);

    $related = app(RelatedContent::class)->for(
        SiteCase::published()->latest('published_at'),
        $record,
        ['style' => $record->style]
    );

    expect($related)->toHaveCount(RelatedContent::LIMIT);
});

it('内容不够时按实际条数返回而不报错', function () {
    $record = SiteCase::factory()->create();
    SiteCase::factory()->create();

    $related = app(RelatedContent::class)->for(
        SiteCase::published()->latest('published_at'),
        $record
    );

    expect($related)->toHaveCount(1);
});

it('推荐结果不含自身且不重复', function () {
    $style  = CaseStyle::cases()[0]->value;
    $record = SiteCase::factory()->create(['style' => $style]);

    // 同风格数量超过上限：第一趟就能填满，补齐趟必须不重复地追加 0 条
    SiteCase::factory()->count(6)->create(['style' => $style]);

    $related = app(RelatedContent::class)->for(
        SiteCase::published()->latest('published_at'),
        $record,
        ['style' => $record->style]
    );

    $keys = $related->modelKeys();

    expect($keys)->not->toContain($record->getKey())
        ->and($keys)->toHaveCount(RelatedContent::LIMIT)
        ->and(array_unique($keys))->toHaveCount(RelatedContent::LIMIT);
});

it('草稿不会进入推荐', function () {
    $record = SiteCase::factory()->create();

    SiteCase::factory()->count(4)->draft()->create();

    $related = app(RelatedContent::class)->for(
        SiteCase::published()->latest('published_at'),
        $record
    );

    expect($related)->toBeEmpty();
});

it('值为空的亲和维度被忽略', function () {
    // 两条都没有分类。若把 category_id => null 也当亲和条件，
    // 「都没填分类」会被算成相关——那不是相关，只是都缺数据
    $record = SiteCase::factory()->create(['category_id' => null, 'style' => CaseStyle::cases()[0]->value]);
    $other  = SiteCase::factory()->create(['category_id' => null, 'style' => CaseStyle::cases()[1]->value]);

    $related = app(RelatedContent::class)->for(
        SiteCase::published()->latest('published_at'),
        $record,
        ['category_id' => $record->category_id]
    );

    // 仍然出现，但走的是「最新补齐」而不是亲和命中——这里锁的是不报错且不漏
    expect($related->modelKeys())->toBe([$other->getKey()]);
});

it('共享标签的方案被认作相关', function () {
    $tag = SiteTag::factory()->create();

    $record = SiteSolution::factory()->create();
    $record->tags()->attach($tag);

    $tagged = SiteSolution::factory()->create(['published_at' => now()->subYear()]);
    $tagged->tags()->attach($tag);

    // 三条更新的无标签方案：只按时间取会把 $tagged 挤出去
    SiteSolution::factory()->count(3)->create();

    $related = app(RelatedContent::class)->for(
        SiteSolution::published()->latest('published_at'),
        $record
    );

    expect($related->modelKeys())->toContain($tagged->getKey());
});

it('同品牌的产品被认作相关', function () {
    $record = SiteProduct::factory()->create(['brand' => '晴空智能', 'sort' => 0]);

    // sort 排在最后，只按排序取不会选中它
    $sameBrand = SiteProduct::factory()->create(['brand' => '晴空智能', 'sort' => 999]);

    SiteProduct::factory()->count(4)->create(['brand' => '别家品牌', 'sort' => 1]);

    $related = app(RelatedContent::class)->for(
        SiteProduct::published()->orderBy('sort')->latest('id'),
        $record,
        ['category_id' => $record->category_id, 'brand' => $record->brand]
    );

    expect($related->modelKeys())->toContain($sameBrand->getKey());
});

it('未发布产品不会进入推荐', function () {
    $record = SiteProduct::factory()->create(['brand' => '晴空智能']);

    SiteProduct::factory()->count(3)->unpublished()->create(['brand' => '晴空智能']);

    $related = app(RelatedContent::class)->for(
        SiteProduct::published()->orderBy('sort')->latest('id'),
        $record,
        ['brand' => $record->brand]
    );

    expect($related)->toBeEmpty();
});

// ---------------------------------------------------------------------------
// 前台渲染（双主题）
// ---------------------------------------------------------------------------

it('案例详情页渲染相关案例', function (string $theme) {
    switchThemeForRelated($theme);

    $record = SiteCase::factory()->create(['slug' => 'related-case-main', 'title_zh' => '主案例']);
    SiteCase::factory()->create(['slug' => 'related-case-other', 'title_zh' => '邻居家案例']);

    $this->get('/cases/'.$record->slug)
        ->assertOk()
        ->assertSee('相关案例')
        ->assertSee('邻居家案例');
})->with(['decoration', 'tech-product']);

it('方案详情页渲染相关方案', function (string $theme) {
    switchThemeForRelated($theme);

    $record = SiteSolution::factory()->create(['slug' => 'related-solution-main', 'title_zh' => '主方案']);
    SiteSolution::factory()->create(['slug' => 'related-solution-other', 'title_zh' => '全屋照明方案']);

    $this->get('/solutions/'.$record->slug)
        ->assertOk()
        ->assertSee('相关方案')
        ->assertSee('全屋照明方案');
})->with(['decoration', 'tech-product']);

it('产品详情页渲染相关产品', function (string $theme) {
    switchThemeForRelated($theme);

    $record = SiteProduct::factory()->create(['slug' => 'related-product-main', 'title_zh' => '主产品']);
    SiteProduct::factory()->create(['slug' => 'related-product-other', 'title_zh' => '智能门锁 Pro']);

    $this->get('/products/'.$record->slug)
        ->assertOk()
        ->assertSee('相关产品')
        ->assertSee('智能门锁 Pro');
})->with(['decoration', 'tech-product']);

it('只有一条内容时详情页不渲染相关区块', function (string $theme) {
    switchThemeForRelated($theme);

    $record = SiteCase::factory()->create(['slug' => 'lonely-case']);

    $this->get('/cases/'.$record->slug)
        ->assertOk()
        ->assertDontSee('相关案例');
})->with(['decoration', 'tech-product']);
