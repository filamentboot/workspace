<?php

/**
 * 官网前台路由测试桩（SiteFrontRoutingTest）
 *
 * Wave 0 安全网测试，由 Plan 10-04（前台路由注册）落地转绿。
 * 覆盖 Pitfall 1：插件禁用时前台路由不应被注册（避免接管现有 web.php 路由）。
 *
 * @group site
 * @covers \LaravelStack\FilamentAdminSite\SiteServiceProvider
 */

/**
 * 目标可观测信号：插件启用时 GET / 命中 site.home 路由，响应 200
 * （由 Plan 10-04 registerFrontend() 实现 loadRoutesFrom 后落地转绿）
 */
it('插件启用时前台路由接管首页', function () {
    $this->markTestIncomplete(
        '待 10-04 落地：插件启用时 GET / 应命中 site.home 路由返回 200'
    );
});

/**
 * 目标可观测信号：插件禁用时 Route::has(\'site.home\') 为 false 或 / 不返回 site 内容
 * （Pitfall 1 验证：catch 分支已 return，registerFrontend 不被调用；由 Plan 10-04 落地转绿）
 */
it('插件禁用时前台路由不被注册', function () {
    $this->markTestIncomplete(
        '待 10-04 落地（Pitfall 1 验证）：插件禁用时 Route::has(\'site.home\') 应为 false'
    );
});
