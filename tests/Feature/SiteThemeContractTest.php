<?php

use Filament\Facades\Filament;
use Filamentboot\FilamentbootSite\Cms\Enums\PageStatus;
use Filamentboot\FilamentbootSite\Cms\Models\SiteMenu;
use Filamentboot\FilamentbootSite\Cms\Models\SiteMenuItem;
use Filamentboot\FilamentbootSite\Cms\Models\SitePage;
use Filamentboot\FilamentbootSite\Cms\Services\MenuResolver;
use Filamentboot\FilamentbootSite\Cms\Themes\ThemeContract;
use Filamentboot\FilamentbootSite\Cms\Themes\ThemeManifest;
use Filamentboot\FilamentbootSite\Cms\Themes\ThemeSwitchCheck;
use Filamentboot\FilamentbootSite\Filament\Pages\SiteSettingsPage;
use Filamentboot\FilamentbootSite\Settings\SiteSettings;
use Filamentboot\FilamentbootSite\SitePlugin;
use Filamentboot\Models\AdminUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

/**
 * 主题契约与切换预检查（#28）
 *
 * 覆盖场景：
 * - 两套主题的 theme.php 清单能被读到，且声明与实际视图文件一致
 * - 清单缺失时按目录推断，features 一律按不支持
 * - 切换预检查按目标主题清单算出会掉内容的已发布页面，草稿不计
 * - 设置页在未确认时拒绝保存，勾了确认才放行
 * - MenuResolver 按主题的 nested_menu 决定嵌套还是摊平
 *
 * @group site
 */
beforeEach(function () {
    ThemeManifest::flush();
});

afterEach(function () {
    ThemeManifest::flush();
});

/**
 * 两套主题的清单都读得到，且声明的区块与实际视图文件一一对应
 *
 * 清单是手工维护的，漏声明会让预检查把已支持的区块报成不支持（误报），
 * 多声明则会漏报——切完主题内容悄悄消失。所以直接跟文件系统对账。
 */
it('两套主题的清单与实际视图文件一致', function (string $theme) {
    $manifest = ThemeManifest::for($theme);

    expect($manifest)->toBeInstanceOf(ThemeContract::class)
        ->and($manifest->key())->toBe($theme)
        ->and($manifest->label())->not->toBe('');

    $base = base_path("packages/filamentboot-site/resources/views/themes/{$theme}");

    $blockFiles = collect(glob($base.'/blocks/*.blade.php') ?: [])
        ->map(fn (string $path): string => basename($path, '.blade.php'))
        ->sort()
        ->values()
        ->all();

    expect(collect($manifest->blocks())->sort()->values()->all())->toBe($blockFiles);

    // templates 里除 default 之外的每一项都要有对应视图（default 走 pages/show）
    foreach ($manifest->templates() as $template) {
        if ($template === 'default') {
            continue;
        }

        expect(is_file($base."/pages/templates/{$template}.blade.php"))
            ->toBeTrue("主题 {$theme} 声明了版式 {$template} 但没有对应视图");
    }
})->with(['decoration', 'tech-product']);

/**
 * 清单缺失时按目录推断，且不敢声明任何 feature
 */
it('无清单的主题按目录推断且默认不支持任何能力', function () {
    $manifest = ThemeManifest::for('theme-that-does-not-exist');

    expect($manifest->templates())->toBe(['default'])
        ->and($manifest->blocks())->toBe([])
        ->and($manifest->supports('nested_menu'))->toBeFalse()
        // 清单没有 label 时回落到 config 白名单，白名单里也没有就用 key 本身
        ->and($manifest->label())->toBe('theme-that-does-not-exist');
});

/**
 * 预检查按目标主题清单列出会掉内容的已发布页面
 */
it('预检查列出目标主题不支持的已发布页面', function () {
    // decoration 清单里没有 wall-of-text 这个区块，也没有 fancy 这个版式
    SitePage::factory()->create([
        'slug'     => 'has-unknown-block',
        'title_zh' => '用了未知区块的页面',
        'blocks'   => [
            ['type' => 'hero', 'data' => ['title' => '正常区块']],
            ['type' => 'wall-of-text', 'data' => []],
        ],
    ]);

    SitePage::factory()->create([
        'slug'     => 'has-unknown-template',
        'title_zh' => '用了未知版式的页面',
        'template' => 'fancy',
    ]);

    // 全部支持的页面不该出现在结果里
    SitePage::factory()->create([
        'slug'     => 'all-good',
        'title_zh' => '完全支持的页面',
        'template' => 'landing',
        'blocks'   => [['type' => 'cta', 'data' => []]],
    ]);

    $result = app(ThemeSwitchCheck::class)->inspect('decoration');

    expect($result['blocks'])->toBe(['wall-of-text'])
        ->and($result['templates'])->toBe(['fancy'])
        ->and($result['pages'])->toHaveCount(2);

    $slugs = array_column($result['pages'], 'slug');

    expect($slugs)->toContain('has-unknown-block')
        ->and($slugs)->toContain('has-unknown-template')
        ->and($slugs)->not->toContain('all-good');

    expect(app(ThemeSwitchCheck::class)->passes('decoration'))->toBeFalse();
});

/**
 * 草稿不进预检查
 *
 * 草稿现在不对外，等它发布时目标主题可能又变了。拿草稿拦切换会让「清掉草稿
 * 才能换主题」变成常态。
 */
it('预检查忽略草稿页面', function () {
    SitePage::factory()->draft()->create([
        'slug'   => 'draft-with-unknown-block',
        'blocks' => [['type' => 'wall-of-text', 'data' => []]],
    ]);

    expect(app(ThemeSwitchCheck::class)->passes('decoration'))->toBeTrue();
});

/**
 * 两套主题都完全支持现有内容时预检查放行
 */
it('内容全被支持时预检查放行', function (string $theme) {
    SitePage::factory()->create([
        'slug'     => 'normal-page',
        'template' => 'default',
        'blocks'   => [['type' => 'hero', 'data' => []], ['type' => 'faq', 'data' => ['items' => []]]],
    ]);

    expect(app(ThemeSwitchCheck::class)->passes($theme))->toBeTrue();
})->with(['decoration', 'tech-product']);

/**
 * 登录一个能改设置的管理员并把插件注册进面板
 */
function loginSettingsAdmin(): AdminUser
{
    $panel = Filament::getPanel('admin');
    $panel->plugin(SitePlugin::make());

    require base_path('vendor/filament/filament/routes/web.php');

    app('router')->getRoutes()->refreshNameLookups();
    app('router')->getRoutes()->refreshActionLookups();

    Filament::setCurrentPanel($panel);

    $user = AdminUser::factory()->create();
    $user->assignRole(
        Role::firstOrCreate([
            'name'       => config('filamentboot.super_admin_role', 'super_admin'),
            'guard_name' => 'admin',
        ])
    );

    test()->actingAs($user, 'admin');

    return $user;
}

/**
 * 未勾确认时切主题被拒，整份设置都不保存
 */
it('未确认时主题切换被拒且设置不保存', function () {
    loginSettingsAdmin();

    app(SiteSettings::class)->fill(['active_theme' => 'decoration'])->save();

    SitePage::factory()->create([
        'slug'   => 'blocking-page',
        'blocks' => [['type' => 'wall-of-text', 'data' => []]],
    ]);

    Livewire::test(SiteSettingsPage::class)
        ->set('data.active_theme', 'tech-product')
        ->set('data.company_name_zh', '改过的公司名')
        ->set('data.confirm_theme_switch', false)
        ->call('save');

    // 主题没换，同一次提交里的其它字段也一并没保存
    expect(app(SiteSettings::class)->active_theme)->toBe('decoration')
        ->and(app(SiteSettings::class)->company_name_zh)->not->toBe('改过的公司名');
});

/**
 * 勾了确认就放行
 */
it('勾了确认后主题切换放行', function () {
    loginSettingsAdmin();

    app(SiteSettings::class)->fill(['active_theme' => 'decoration'])->save();

    SitePage::factory()->create([
        'slug'   => 'blocking-page',
        'blocks' => [['type' => 'wall-of-text', 'data' => []]],
    ]);

    Livewire::test(SiteSettingsPage::class)
        ->set('data.active_theme', 'tech-product')
        ->set('data.confirm_theme_switch', true)
        ->call('save');

    expect(app(SiteSettings::class)->active_theme)->toBe('tech-product');
});

/**
 * 目标主题完全支持时不需要确认
 */
it('目标主题完全支持时无需确认即可切换', function () {
    loginSettingsAdmin();

    app(SiteSettings::class)->fill(['active_theme' => 'decoration'])->save();

    SitePage::factory()->create([
        'slug'   => 'fine-page',
        'blocks' => [['type' => 'hero', 'data' => []]],
    ]);

    Livewire::test(SiteSettingsPage::class)
        ->set('data.active_theme', 'tech-product')
        ->call('save');

    expect(app(SiteSettings::class)->active_theme)->toBe('tech-product');
});

/**
 * 建一条带二级项的 main 菜单
 *
 * @return array{parent: SiteMenuItem, child: SiteMenuItem}
 */
function seedNestedMenu(): array
{
    $menu = SiteMenu::create(['key' => 'main', 'name' => '顶部导航']);

    $parent = SiteMenuItem::create([
        'menu_id'   => $menu->getKey(),
        'parent_id' => SiteMenuItem::defaultParentKey(),
        'type'      => 'anchor',
        'label'     => '关于我们',
        'target'    => '#about',
        'sort'      => 0,
    ]);

    $child = SiteMenuItem::create([
        'menu_id'   => $menu->getKey(),
        'parent_id' => $parent->getKey(),
        'type'      => 'anchor',
        'label'     => '团队介绍',
        'target'    => '#team',
        'sort'      => 0,
    ]);

    MenuResolver::forget();

    return ['parent' => $parent, 'child' => $child];
}

/**
 * 主题支持二级时返回嵌套结构
 */
it('主题支持二级导航时返回嵌套结构', function () {
    seedNestedMenu();

    app(SiteSettings::class)->fill(['active_theme' => 'decoration'])->save();
    ThemeManifest::flush();

    $items = app(MenuResolver::class)->resolve('main');

    expect($items)->toHaveCount(1)
        ->and($items[0]['label'])->toBe('关于我们')
        ->and($items[0]['children'])->toHaveCount(1)
        ->and($items[0]['children'][0]['label'])->toBe('团队介绍');
});

/**
 * 主题不支持二级时摊平而不是丢弃
 *
 * 丢弃等于后台配好的入口在前台静默消失，那正是本条契约要防的事。
 */
it('主题不支持二级导航时摊平而非丢弃', function () {
    seedNestedMenu();

    // 用一个没有清单的主题名冒充「不支持嵌套」的主题：ThemeManifest 对无清单
    // 主题一律按 features 全不支持处理，正好是要测的分支
    config()->set('filamentboot-site.themes.flat-theme', '假的平铺主题');
    app(SiteSettings::class)->fill(['active_theme' => 'flat-theme'])->save();
    ThemeManifest::flush();

    $items = app(MenuResolver::class)->resolve('main');

    expect($items)->toHaveCount(2)
        ->and($items[0]['label'])->toBe('关于我们')
        ->and($items[0]['children'])->toBe([])
        ->and($items[1]['label'])->toBe('团队介绍');
});

/**
 * 页脚永远拿摊平结果，不跟随主题
 */
it('页脚解析永远摊平', function () {
    $menu = SiteMenu::create(['key' => 'footer', 'name' => '页脚导航']);

    $parent = SiteMenuItem::create([
        'menu_id'   => $menu->getKey(),
        'parent_id' => SiteMenuItem::defaultParentKey(),
        'type'      => 'anchor',
        'label'     => '服务',
        'target'    => '#service',
        'sort'      => 0,
    ]);

    SiteMenuItem::create([
        'menu_id'   => $menu->getKey(),
        'parent_id' => $parent->getKey(),
        'type'      => 'anchor',
        'label'     => '售后',
        'target'    => '#support',
        'sort'      => 0,
    ]);

    MenuResolver::forget();

    // decoration 支持嵌套，但页脚仍应拿到两条平铺项
    app(SiteSettings::class)->fill(['active_theme' => 'decoration'])->save();
    ThemeManifest::flush();

    $items = app(MenuResolver::class)->resolveFlat('footer');

    expect($items)->toHaveCount(2)
        ->and(array_column($items, 'label'))->toBe(['服务', '售后']);
});

/**
 * 父项解析不出地址时整支不渲染
 */
it('父项不可用时子项一并不渲染', function () {
    $menu = SiteMenu::create(['key' => 'main', 'name' => '顶部导航']);

    // 指向一个不存在的页面 id → href 解析为 null
    $parent = SiteMenuItem::create([
        'menu_id'   => $menu->getKey(),
        'parent_id' => SiteMenuItem::defaultParentKey(),
        'type'      => 'page',
        'label'     => '指向已删页面',
        'target'    => '999999',
        'sort'      => 0,
    ]);

    SiteMenuItem::create([
        'menu_id'   => $menu->getKey(),
        'parent_id' => $parent->getKey(),
        'type'      => 'anchor',
        'label'     => '孤儿子项',
        'target'    => '#orphan',
        'sort'      => 0,
    ]);

    MenuResolver::forget();
    app(SiteSettings::class)->fill(['active_theme' => 'decoration'])->save();
    ThemeManifest::flush();

    expect(app(MenuResolver::class)->resolve('main'))->toBeNull();
});

/**
 * 已发布页面用 landing 版式时状态机与可见性不受影响
 */
it('landing 版式页面正常发布可见', function () {
    SitePage::factory()->create([
        'slug'     => 'landing-page',
        'title_zh' => '落地页',
        'template' => 'landing',
        'status'   => PageStatus::PUBLISHED,
    ]);

    expect(SitePage::published()->where('slug', 'landing-page')->exists())->toBeTrue();
});
