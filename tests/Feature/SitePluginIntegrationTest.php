<?php

/**
 * 官网插件集成测试桩（SitePluginIntegrationTest）
 *
 * Wave 0 安全网测试，由 Plan 10-04（monorepo 集成）落地转绿。
 * 测试目标可观测信号：
 * - plugin:scan 执行后 plugins 表含 slug=filament-admin-site 的记录
 * - SitePlugin 注册成功（plugin_class 字段非空）
 *
 * @group site
 * @covers \LaravelStack\FilamentAdminSite\SitePlugin
 * @covers \LaravelStack\FilamentAdminSite\SiteServiceProvider
 */

/**
 * 目标可观测信号：php artisan plugin:scan 后 plugins 表含 slug=filament-admin-site、
 * plugin_class=SitePlugin 的记录（由 Plan 10-04 monorepo 集成后落地转绿）
 */
it('plugin:scan 发现官网插件包', function () {
    $this->markTestIncomplete(
        '待 10-04 落地：plugin:scan 执行后 plugins 表应含 slug=filament-admin-site 且 plugin_class 非空'
    );
});

/**
 * 目标可观测信号：启用插件后 SitePlugin::register() 执行、SiteSettingsPage 注册到 Filament Panel
 * （由 Plan 10-04 monorepo 集成 + AdminPanelProvider 注册 SitePlugin 后落地转绿）
 */
it('插件启用时 SitePlugin 注册成功', function () {
    $this->markTestIncomplete(
        '待 10-04 落地：插件启用后 SitePlugin::register() 应执行、SiteSettingsPage 应注册到 Filament Panel'
    );
});
