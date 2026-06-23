<?php

use Filament\Panel;
use Filamentboot\FilamentbootSite\Filament\Pages\SiteSettingsPage;
use Filamentboot\FilamentbootSite\Filament\Resources\ContactMessageResource;
use Filamentboot\FilamentbootSite\Filament\Resources\SiteCaseResource;
use Filamentboot\FilamentbootSite\SitePlugin;
use Filamentboot\FilamentbootSite\SiteServiceProvider;
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
