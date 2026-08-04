<?php

use Filament\Panel;
use Filamentboot\FilamentbootSite\Database\Seeders\SiteMenuSeeder;
use Filamentboot\FilamentbootSite\Filament\Pages\SiteSettingsPage;
use Filamentboot\FilamentbootSite\Filament\Resources\ContactMessageResource;
use Filamentboot\FilamentbootSite\Modules\Corporate\Cases\Filament\SiteCaseResource;
use Filamentboot\FilamentbootSite\SitePlugin;
use Filamentboot\FilamentbootSite\SiteServiceProvider;
use Filamentboot\Models\Menu;
use Illuminate\Support\Facades\Artisan;

/**
 * 官网插件集成测试（SitePluginIntegrationTest）
 *
 * 覆盖场景：
 * - plugin:scan 执行后 plugins 表含 slug=filamentboot-site 的记录（SITE-04）
 * - SitePlugin + SiteServiceProvider 类存在且接口正确（包发现验证）
 * - SitePlugin::register() 注册 SiteSettingsPage 到 Filament Panel
 *
 * @group site
 *
 * @covers \Filamentboot\FilamentbootSite\SitePlugin
 * @covers \Filamentboot\FilamentbootSite\SiteServiceProvider
 */

/**
 * plugin:scan 执行后 plugins 表含 slug=filamentboot-site、plugin_class=SitePlugin 的记录（SITE-04）
 */
it('plugin:scan 发现官网插件包', function () {
    // 执行 plugin:scan，扫描所有已注册包的 extra.filament-admin 契约
    Artisan::call('plugin:scan');
    $output = Artisan::output();

    // 断言输出中提到了 filamentboot-site（D-10-01 契约：slug=filamentboot-site）
    // 或者 plugins 表中已存在该记录
    $plugin = DB::table('plugins')
        ->where('slug', 'filamentboot-site')
        ->first();

    expect($plugin)->not->toBeNull('plugin:scan 应将 filamentboot-site 写入 plugins 表');
    expect($plugin->plugin_class)->toBe(
        SitePlugin::class,
        'plugin_class 应指向 Filamentboot\\FilamentbootSite\\SitePlugin'
    );
    expect($plugin->service_provider)->toBe(
        SiteServiceProvider::class,
        'service_provider 应指向 SiteServiceProvider'
    );
});

/**
 * 插件启用时 SitePlugin 注册成功（SiteSettingsPage 注册到 Filament Panel）
 */
it('插件启用时 SitePlugin 注册成功', function () {
    // 直接实例化 Panel 并注册 SitePlugin，验证注册契约（与 SiteCaseResourceTest 同一模式）
    $panel = new Panel;
    SitePlugin::make()->register($panel);

    // 断言 SiteSettingsPage 已注册到 panel
    $pages = $panel->getPages();

    // 断言 SiteSettingsPage 类名在 pages 数组中（getPages 返回类字符串列表）
    expect($pages)->toContain(SiteSettingsPage::class);

    // 断言五个 Resource 已注册
    $resources = $panel->getResources();
    expect($resources)->toContain(SiteCaseResource::class);
    expect($resources)->toContain(ContactMessageResource::class);
});

/**
 * 每个要进导航的官网资源都在 menus 表有登记行
 *
 * 后台侧边栏由 AdminNavigationBuilder 从主包 menus 表构建，Filament 基于 Resource
 * 静态属性自动生成导航的机制被 Panel 的 ->navigation() 回调整体旁路。因此漏登记的
 * 资源即使路由已注册，也只能靠直链访问——#17 的「导航菜单」与 #18 的「重定向」
 * 就这样在侧边栏里隐身了一整轮，而当时的验证全走 Livewire::test()，看不到侧边栏。
 *
 * 这条是结构性护栏：以后新增资源忘了改 SiteMenuSeeder，这里就会红。
 */
it('要进导航的官网资源都在后台菜单表有登记行', function () {
    (new SiteMenuSeeder)->run();

    $panel = new Panel;
    SitePlugin::make()->register($panel);

    $missing = [];

    foreach ($panel->getResources() as $resource) {
        // 只管本包的资源；shouldRegisterNavigation() 为 false 的（如菜单项树页，
        // 必须带 ?menu= 才有上下文）本就不该出现在侧边栏
        if (! str_starts_with($resource, 'Filamentboot\\FilamentbootSite\\')) {
            continue;
        }

        if (! $resource::shouldRegisterNavigation()) {
            continue;
        }

        $routeName = $resource::getRouteBaseName().'.index';

        if (! Menu::query()->where('route_name', $routeName)->exists()) {
            $missing[] = $routeName;
        }
    }

    expect($missing)->toBe([]);
});
