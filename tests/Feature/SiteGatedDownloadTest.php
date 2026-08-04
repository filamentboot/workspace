<?php

use Filamentboot\FilamentbootSite\Cms\Blocks\GatedDownloadBlock;
use Filamentboot\FilamentbootSite\Cms\Enums\PageStatus;
use Filamentboot\FilamentbootSite\Cms\Models\SitePage;
use Filamentboot\FilamentbootSite\Cms\Services\GatedAssetRegistry;
use Filamentboot\FilamentbootSite\Models\ContactMessage;
use Filamentboot\FilamentbootSite\Settings\SiteSettings;
use Filamentboot\FilamentbootSite\SiteServiceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Testing\TestResponse;

uses(RefreshDatabase::class);

/**
 * 资料索取 / gated content（手册换联系方式）
 *
 * 这份文件锁的是「门关得住」这件事，它由四条共同成立，缺一条整个功能就变成
 * 「多点了一次鼠标的公开下载」：
 *
 *   1. 前台 HTML 里**没有文件路径**，只有不透明 key
 *   2. 下载链接必须带**有效签名**，且有时限
 *   3. key 必须在登记表里，登记表只收**已发布**页面声明的资料（草稿页的资料下不到）
 *   4. 判为机器人时对外回成功但**不放资料**——否则蜜罐就成了绕过留资的后门
 *
 * @group site
 */
beforeEach(function () {
    config([
        'filamentboot-site.route.mode'    => 'root',
        'filamentboot-site.default_theme' => 'decoration',
        'filamentboot-site.gated.disk'    => 'gated-test',
    ]);

    Storage::fake('gated-test');

    $provider = new SiteServiceProvider(app());

    foreach (['registerThemeViews', 'shareSiteSettings'] as $method) {
        (new ReflectionMethod(SiteServiceProvider::class, $method))->invoke($provider);
    }

    require base_path('packages/filamentboot-site/routes/site.php');

    app('router')->getRoutes()->refreshNameLookups();
    app('router')->getRoutes()->refreshActionLookups();

    RateLimiter::clear('site-contact:127.0.0.1');
    GatedAssetRegistry::forget();
});

/**
 * 切换前台主题并重建视图命名空间（各文件各写一份，见 SiteRelatedContentTest 的注释）
 */
function switchThemeForGated(string $theme): void
{
    $settings               = app(SiteSettings::class);
    $settings->active_theme = $theme;
    app()->instance(SiteSettings::class, $settings);

    (new ReflectionMethod(SiteServiceProvider::class, 'registerThemeViews'))
        ->invoke(new SiteServiceProvider(app()));

    app('view')->flushFinderCache();
}

/**
 * 建一个带资料索取区块的页面，并把文件放到假磁盘上
 *
 * @param  array<string, mixed>  $overrides  区块 data 覆盖项
 */
function gatedPage(array $overrides = [], bool $published = true): SitePage
{
    $path = 'gated-assets/manual.pdf';

    Storage::disk('gated-test')->put($path, '%PDF-1.4 假手册内容');

    $page = SitePage::factory()->create([
        'slug'     => 'gated-page',
        'title_zh' => '资料页',
        'status'   => $published ? PageStatus::PUBLISHED : PageStatus::DRAFT,
        'blocks'   => [[
            'type' => 'gated-download',
            'data' => [
                'title'       => '全屋智能选型手册',
                'description' => '二十页，讲清各价位该怎么选',
                'file'        => $path,
                'source'      => 'ebook-selection',
                ...$overrides,
            ],
        ]],
    ]);

    GatedAssetRegistry::forget();

    return $page;
}

/**
 * 提交一次资料索取
 *
 * @param  array<string, mixed>  $overrides
 */
function requestAsset(string $assetKey, array $overrides = []): TestResponse
{
    return test()->postJson(route('site.contact.store'), [
        'name'    => '张三',
        'phone'   => '13800138000',
        'elapsed' => 5,
        'asset'   => $assetKey,
        ...$overrides,
    ]);
}

// ---------------------------------------------------------------------------
// 登记表
// ---------------------------------------------------------------------------

it('已发布页面声明的资料进入登记表', function () {
    gatedPage();

    $key = GatedAssetRegistry::key('gated-assets/manual.pdf');

    expect(app(GatedAssetRegistry::class)->find($key))
        ->toBe(['path' => 'gated-assets/manual.pdf', 'label' => '全屋智能选型手册']);
});

it('草稿页面声明的资料不进登记表', function () {
    // 页面还没对外，链接也不该生效
    gatedPage(published: false);

    expect(app(GatedAssetRegistry::class)->all())->toBe([]);
});

it('没上传文件的区块不进登记表', function () {
    gatedPage(['file' => '']);

    expect(app(GatedAssetRegistry::class)->all())->toBe([]);
});

it('key 是确定性的：同一路径每次同一个 key', function () {
    // 随机 token 会让每次响应的 HTML 都不同，整页缓存直接失效（#29）
    expect(GatedAssetRegistry::key('a/b.pdf'))
        ->toBe(GatedAssetRegistry::key('a/b.pdf'))
        ->and(GatedAssetRegistry::key('a/b.pdf'))
        ->not->toBe(GatedAssetRegistry::key('a/c.pdf'));
});

it('页面保存后登记表缓存自动失效', function () {
    $page = gatedPage();

    expect(app(GatedAssetRegistry::class)->all())->toHaveCount(1);

    // 下线这个页面：不清缓存的话资料还能继续下载
    $page->update(['status' => PageStatus::DRAFT]);

    expect(app(GatedAssetRegistry::class)->all())->toBe([]);
});

it('页面删除后登记表缓存自动失效', function () {
    $page = gatedPage();
    expect(app(GatedAssetRegistry::class)->all())->toHaveCount(1);

    $page->delete();

    expect(app(GatedAssetRegistry::class)->all())->toBe([]);
});

// ---------------------------------------------------------------------------
// 前台渲染：门的第一道
// ---------------------------------------------------------------------------

it('前台不输出文件路径，只出不透明 key', function (string $theme) {
    switchThemeForGated($theme);
    gatedPage();

    $html = (string) $this->get('/gated-page')->assertOk()->getContent();

    expect($html)->toContain('全屋智能选型手册')
        ->and($html)->toContain('二十页，讲清各价位该怎么选')
        // 门的第一道：源码里看不到路径
        ->and($html)->not->toContain('manual.pdf')
        ->and($html)->not->toContain('gated-assets')
        // 只有 key
        ->and($html)->toContain(GatedAssetRegistry::key('gated-assets/manual.pdf'));
})->with(['decoration', 'tech-product']);

it('资料页仍然可整页缓存', function () {
    gatedPage();

    $response = $this->get('/gated-page')->assertOk();

    // key 是确定性的，所以带资料索取的页面照样能进共享缓存
    expect($response->headers->getCookies())->toBe([])
        ->and($response->headers->get('Cache-Control'))->toContain('public');
});

it('文件未上传时退化成普通询盘表单', function (string $theme) {
    switchThemeForGated($theme);
    gatedPage(['file' => '']);

    $html = (string) $this->get('/gated-page')->assertOk()->getContent();

    // 联系方式照收，但不出下载相关的东西
    expect($html)->toContain('姓名')
        ->and($html)->not->toContain('asset:');
})->with(['decoration', 'tech-product']);

// ---------------------------------------------------------------------------
// 提交 → 拿链接 → 下载
// ---------------------------------------------------------------------------

it('提交后返回限时下载链接并记下索取的资料', function () {
    gatedPage();

    $key = GatedAssetRegistry::key('gated-assets/manual.pdf');

    $response = requestAsset($key)->assertOk();

    $download = (string) $response->json('download');

    expect($response->json('ok'))->toBeTrue()
        ->and($response->json('filename'))->toBe('全屋智能选型手册')
        ->and($download)->toContain('/downloads/'.$key)
        ->and($download)->toContain('signature=');

    // 销售看到的第一眼就该知道这条线索是「下载了选型手册」来的
    expect(ContactMessage::sole()->extra)
        ->toBe([['label' => '索取资料', 'value' => '全屋智能选型手册']]);
});

it('签名链接可下载到文件且不进共享缓存', function () {
    gatedPage();

    $download = (string) requestAsset(GatedAssetRegistry::key('gated-assets/manual.pdf'))
        ->assertOk()->json('download');

    $response = $this->get($download)->assertOk();

    expect((string) $response->headers->get('Cache-Control'))->toContain('no-store')
        ->and((string) $response->headers->get('Cache-Control'))->not->toContain('public')
        ->and($response->streamedContent())->toContain('假手册内容');
});

it('无签名直接访问下载地址被拒', function () {
    gatedPage();

    // 门的第二道：知道 key 也下不到
    $this->get('/downloads/'.GatedAssetRegistry::key('gated-assets/manual.pdf'))
        ->assertForbidden();
});

it('签名过期后下载被拒', function () {
    gatedPage();

    $url = URL::temporarySignedRoute(
        'site.download',
        now()->subMinute(),
        ['asset' => GatedAssetRegistry::key('gated-assets/manual.pdf')]
    );

    $this->get($url)->assertForbidden();
});

it('签名有效但 key 未登记时 404', function () {
    gatedPage();

    // 页面下线、换了文件、或链接被改了几个字符
    $url = URL::temporarySignedRoute(
        'site.download',
        now()->addMinutes(30),
        ['asset' => str_repeat('a', 16)]
    );

    $this->get($url)->assertNotFound();
});

it('登记表有记录但文件已被删时 404 而不是 500', function () {
    gatedPage();

    $download = (string) requestAsset(GatedAssetRegistry::key('gated-assets/manual.pdf'))
        ->assertOk()->json('download');

    Storage::disk('gated-test')->delete('gated-assets/manual.pdf');

    $this->get($download)->assertNotFound();
});

it('判为机器人时回成功但不给下载链接', function () {
    gatedPage();

    // 门的第四道：蜜罐不能成为「不留真联系方式也能拿手册」的后门
    $response = requestAsset(
        GatedAssetRegistry::key('gated-assets/manual.pdf'),
        ['website' => 'https://spam.example']
    )->assertOk();

    expect($response->json('ok'))->toBeTrue()
        ->and($response->json('download'))->toBeNull()
        ->and(ContactMessage::count())->toBe(0);
});

it('未带 asset 的普通询盘不返回下载链接', function () {
    gatedPage();

    $response = $this->postJson(route('site.contact.store'), [
        'name' => '张三', 'phone' => '13800138000', 'elapsed' => 5,
    ])->assertOk();

    expect($response->json('download'))->toBeNull()
        ->and(ContactMessage::sole()->extra)->toBeNull();
});

it('伪造的 asset key 按普通询盘处理', function () {
    gatedPage();

    $response = requestAsset(str_repeat('f', 16))->assertOk();

    // 线索照收（联系方式是真的），但没有资料可放
    expect($response->json('download'))->toBeNull()
        ->and(ContactMessage::sole()->extra)->toBeNull();
});

it('asset 传路径而不是 key 时拿不到任何东西', function () {
    gatedPage();

    // 路由的 where 约束只放行 16 位十六进制，且登记表按 key 查——
    // 双重保险，路径永远解析不成一条资料
    $response = requestAsset('gated-assets/manual.pdf')->assertOk();

    expect($response->json('download'))->toBeNull();

    $this->get('/downloads/../../.env')->assertNotFound();
});

// ---------------------------------------------------------------------------
// 区块自身
// ---------------------------------------------------------------------------

it('区块未上传文件时 assetKey 为空串', function () {
    $block = new GatedDownloadBlock;

    expect($block->assetKey([]))->toBe('')
        ->and($block->assetKey(['file' => '  ']))->toBe('')
        ->and($block->assetKey(['file' => 'a/b.pdf']))->toBe(GatedAssetRegistry::key('a/b.pdf'));
});

it('区块要求资料名与文件必填', function () {
    $errors = (new GatedDownloadBlock)->validate([]);

    expect($errors)->toHaveKeys(['title', 'file']);
});

it('区块上传白名单不含可被浏览器渲染的类型', function () {
    // html / svg 会被浏览器当页面渲染，等于在自己域名下托管别人的 HTML
    expect(GatedDownloadBlock::ACCEPTED_TYPES)
        ->not->toContain('text/html')
        ->and(GatedDownloadBlock::ACCEPTED_TYPES)->not->toContain('image/svg+xml')
        ->and(GatedDownloadBlock::ACCEPTED_TYPES)->toContain('application/pdf');
});
