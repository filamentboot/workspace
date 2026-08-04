<?php

use Filamentboot\FilamentbootSite\Cms\Models\SiteMenu;
use Filamentboot\FilamentbootSite\Cms\Models\SiteMenuItem;
use Filamentboot\FilamentbootSite\Cms\Models\SitePage;
use Filamentboot\FilamentbootSite\Cms\Services\MenuResolver;
use Filamentboot\FilamentbootSite\Settings\SiteSettings;
use Filamentboot\FilamentbootSite\SiteServiceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

/**
 * 前台导航菜单解析与接入测试（#17）
 *
 * 覆盖场景：
 * - 四种 type 的地址解析（page / route / url / anchor）
 * - 解析不出地址的项整条不渲染：未发布页面、白名单外路由、被拦下的外链
 * - 无菜单 / 无可渲染项时返回 null，前台回退硬编码列表而**不白屏**（升级安全硬要求）
 * - rememberForever 缓存与模型事件失效
 * - 前台导航与页脚真实同步（双主题各跑一遍）
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
        $reflection = new ReflectionMethod($provider, $method);
        $reflection->setAccessible(true);
        $reflection->invoke($provider);
    }

    app('router')->getRoutes()->refreshNameLookups();
    app('router')->getRoutes()->refreshActionLookups();

    Cache::flush();
});

/**
 * 建一条菜单并挂上若干菜单项
 *
 * @param  list<array<string, mixed>>  $items
 */
function makeMenu(string $key, array $items = []): SiteMenu
{
    $menu = SiteMenu::create(['key' => $key, 'name' => strtoupper($key).' 菜单']);

    foreach ($items as $index => $item) {
        SiteMenuItem::create([
            'menu_id'   => $menu->getKey(),
            'parent_id' => SiteMenuItem::defaultParentKey(),
            'sort'      => $index,
            ...$item,
        ]);
    }

    return $menu;
}

/**
 * 无菜单时返回 null（前台据此回退硬编码列表）
 */
it('无菜单时解析结果为 null', function () {
    expect(app(MenuResolver::class)->resolve('main'))->toBeNull();
});

/**
 * 菜单存在但一项都没有时同样返回 null
 */
it('空菜单解析结果为 null', function () {
    makeMenu('main');

    expect(app(MenuResolver::class)->resolve('main'))->toBeNull();
});

/**
 * page 型解析成已发布页面的 URL，存 id 不存 slug
 */
it('page 型菜单项解析成页面 URL', function () {
    $page = SitePage::factory()->create(['slug' => 'about-us', 'title_zh' => '关于我们']);

    makeMenu('main', [
        ['type' => 'page', 'label' => '关于我们', 'target' => (string) $page->getKey()],
    ]);

    $links = app(MenuResolver::class)->resolve('main');

    expect($links)->toHaveCount(1)
        ->and($links[0]['label'])->toBe('关于我们')
        ->and($links[0]['href'])->toEndWith('/about-us');
});

/**
 * slug 改了菜单不断：存的是 id，解析时才取当前 slug
 */
it('页面改 slug 后菜单跟着走', function () {
    $page = SitePage::factory()->create(['slug' => 'old-slug']);

    makeMenu('main', [
        ['type' => 'page', 'label' => '页面', 'target' => (string) $page->getKey()],
    ]);

    expect(app(MenuResolver::class)->resolve('main')[0]['href'])->toEndWith('/old-slug');

    $page->update(['slug' => 'new-slug']);
    MenuResolver::forget();

    expect(app(MenuResolver::class)->resolve('main')[0]['href'])->toEndWith('/new-slug');
});

/**
 * 未发布页面不出现在导航里（§0.3 第 3 条：草稿绝不泄露到前台）
 */
it('未发布页面的菜单项不渲染', function () {
    $draft = SitePage::factory()->draft()->create(['slug' => 'secret-draft']);

    makeMenu('main', [
        ['type' => 'page', 'label' => '草稿页', 'target' => (string) $draft->getKey()],
    ]);

    expect(app(MenuResolver::class)->resolve('main'))->toBeNull();
});

/**
 * 页面被删后菜单项不渲染，也不报错
 */
it('页面被删后菜单项不渲染', function () {
    $page = SitePage::factory()->create();

    makeMenu('main', [
        ['type' => 'page', 'label' => '要被删的页', 'target' => (string) $page->getKey()],
    ]);

    $page->delete();
    MenuResolver::forget();

    expect(app(MenuResolver::class)->resolve('main'))->toBeNull();
});

/**
 * route 型：白名单内的路由解析成 URL
 */
it('白名单内的 route 型菜单项解析成 URL', function () {
    makeMenu('main', [
        ['type' => 'route', 'label' => '案例', 'target' => 'site.cases.index'],
    ]);

    expect(app(MenuResolver::class)->resolve('main')[0]['href'])->toEndWith('/cases');
});

/**
 * route 型：白名单外的路由名不渲染
 *
 * route() 对未知名称会抛异常，而导航在每个页面都渲染——一个填错的路由名
 * 不能让全站白屏。
 */
it('白名单外的 route 型菜单项不渲染', function () {
    makeMenu('main', [
        ['type' => 'route', 'label' => '后台', 'target' => 'filament.admin.pages.dashboard'],
        ['type' => 'route', 'label' => '瞎填的', 'target' => 'no.such.route'],
    ]);

    expect(app(MenuResolver::class)->resolve('main'))->toBeNull();
});

/**
 * url 型：走 SafeUrl 的 scheme 白名单
 */
it('url 型菜单项过 scheme 白名单', function () {
    makeMenu('main', [
        ['type' => 'url', 'label' => '合法外链', 'target' => 'https://example.com/a'],
        ['type' => 'url', 'label' => '伪协议', 'target' => 'javascript:alert(1)'],
        ['type' => 'url', 'label' => '协议相对', 'target' => '//evil.com'],
    ]);

    $links = app(MenuResolver::class)->resolve('main');

    expect($links)->toHaveCount(1)
        ->and($links[0]['href'])->toBe('https://example.com/a');
});

/**
 * anchor 型必须以 # 开头
 */
it('anchor 型菜单项必须以井号开头', function () {
    makeMenu('main', [
        ['type' => 'anchor', 'label' => '联系', 'target' => '#contact'],
        ['type' => 'anchor', 'label' => '不合法', 'target' => 'contact'],
    ]);

    $links = app(MenuResolver::class)->resolve('main');

    expect($links)->toHaveCount(1)
        ->and($links[0]['href'])->toBe('#contact');
});

/**
 * 未知 type 不渲染
 */
it('未知 type 的菜单项不渲染', function () {
    makeMenu('main', [
        ['type' => 'whatever', 'label' => '未知类型', 'target' => '/somewhere'],
    ]);

    expect(app(MenuResolver::class)->resolve('main'))->toBeNull();
});

/**
 * open_in_new 映射成 target=_blank
 */
it('新窗口打开映射成 target 属性', function () {
    makeMenu('main', [
        ['type' => 'url', 'label' => '外链', 'target' => 'https://example.com', 'open_in_new' => true],
        ['type' => 'anchor', 'label' => '锚点', 'target' => '#a', 'open_in_new' => false],
    ]);

    $links = app(MenuResolver::class)->resolve('main');

    expect($links[0]['target'])->toBe('_blank')
        ->and($links[1]['target'])->toBeNull();
});

/**
 * 按 sort 升序输出
 */
it('菜单项按 sort 排序', function () {
    $menu = makeMenu('main');

    foreach ([['乙', 2], ['甲', 1], ['丙', 3]] as [$label, $sort]) {
        SiteMenuItem::create([
            'menu_id'   => $menu->getKey(),
            'parent_id' => SiteMenuItem::defaultParentKey(),
            'type'      => 'anchor',
            'label'     => $label,
            'target'    => '#'.$sort,
            'sort'      => $sort,
        ]);
    }

    expect(collect(app(MenuResolver::class)->resolve('main'))->pluck('label')->all())
        ->toBe(['甲', '乙', '丙']);
});

/**
 * 解析结果进缓存，改动后由模型事件失效
 */
it('菜单解析结果被缓存且改动后失效', function () {
    $menu = makeMenu('main', [
        ['type' => 'anchor', 'label' => '初始项', 'target' => '#one'],
    ]);

    expect(app(MenuResolver::class)->resolve('main'))->toHaveCount(1)
        ->and(Cache::has(MenuResolver::CACHE_PREFIX.'main'))->toBeTrue();

    // 新增菜单项触发 saved 事件 → 缓存失效
    SiteMenuItem::create([
        'menu_id'   => $menu->getKey(),
        'parent_id' => SiteMenuItem::defaultParentKey(),
        'type'      => 'anchor',
        'label'     => '新增项',
        'target'    => '#two',
        'sort'      => 1,
    ]);

    expect(Cache::has(MenuResolver::CACHE_PREFIX.'main'))->toBeFalse()
        ->and(app(MenuResolver::class)->resolve('main'))->toHaveCount(2);
});

/**
 * 菜单 key 改名时旧键缓存一并清掉
 *
 * 不清就会留一条永不过期的孤儿缓存，日后又建同名菜单会读到旧结构。
 */
it('菜单改 key 时清掉旧键缓存', function () {
    $menu = makeMenu('main', [
        ['type' => 'anchor', 'label' => '项', 'target' => '#a'],
    ]);

    app(MenuResolver::class)->resolve('main');

    expect(Cache::has(MenuResolver::CACHE_PREFIX.'main'))->toBeTrue();

    $menu->update(['key' => 'primary']);

    expect(Cache::has(MenuResolver::CACHE_PREFIX.'main'))->toBeFalse();
});

/**
 * 删除菜单项后缓存失效
 */
it('删除菜单项后缓存失效', function () {
    $menu = makeMenu('main', [
        ['type' => 'anchor', 'label' => '甲', 'target' => '#a'],
        ['type' => 'anchor', 'label' => '乙', 'target' => '#b'],
    ]);

    expect(app(MenuResolver::class)->resolve('main'))->toHaveCount(2);

    $menu->items()->first()->delete();

    expect(app(MenuResolver::class)->resolve('main'))->toHaveCount(1);
});

/**
 * 前台导航渲染后台配置的菜单（双主题各跑一遍）
 */
it('前台导航同步后台菜单', function (string $theme) {
    switchMenuTheme($theme);

    $page = SitePage::factory()->create(['slug' => 'sync-check', 'title_zh' => '同步检查页']);

    makeMenu('main', [
        ['type' => 'page', 'label' => '后台配的导航项', 'target' => (string) $page->getKey()],
        ['type' => 'route', 'label' => '后台配的案例', 'target' => 'site.cases.index'],
    ]);

    $html = $this->get('/')->assertOk()->getContent();

    // 不断言「硬编码项消失」：首页正文与页脚里也有「装修案例」「智能方案」这些字样，
    // 页面级 not->toContain 会被它们误判。菜单是否真的换掉了由解析器层面的
    // 用例覆盖（上面那批 resolve() 断言）。
    expect($html)->toContain('后台配的导航项')
        ->and($html)->toContain('后台配的案例');
})->with('menuThemes');

/**
 * 删光菜单后前台回退硬编码列表且不白屏（升级安全硬要求）
 */
it('无菜单时前台回退硬编码导航', function (string $theme) {
    switchMenuTheme($theme);

    $html = $this->get('/')->assertOk()->getContent();

    expect($html)->toContain('装修案例')
        ->and($html)->toContain('智能方案')
        ->and($html)->toContain('联系我们');
})->with('menuThemes');

/**
 * 页脚读 footer 菜单，与 nav 各自独立
 */
it('页脚读 footer 菜单', function (string $theme) {
    switchMenuTheme($theme);

    makeMenu('footer', [
        ['type' => 'route', 'label' => '页脚专属链接', 'target' => 'site.news.index'],
    ]);

    $html = $this->get('/')->assertOk()->getContent();

    expect($html)->toContain('页脚专属链接')
        // nav 无菜单，仍走硬编码兜底
        ->and($html)->toContain('装修案例');
})->with('menuThemes');

/**
 * 切换前台主题并清掉视图解析缓存
 */
function switchMenuTheme(string $theme): void
{
    $settings               = app(SiteSettings::class);
    $settings->active_theme = $theme;
    $settings->save();

    $provider   = new SiteServiceProvider(app());
    $reflection = new ReflectionMethod($provider, 'registerThemeViews');
    $reflection->setAccessible(true);
    $reflection->invoke($provider);
}

dataset('menuThemes', ['decoration', 'tech-product']);
