<?php

/**
 * SEO 元数据测试桩（SiteSeoMetaTest）
 *
 * Wave 0 安全网测试，由 Plan 10-04（前台路由）+ Plan 10-05（视图渲染）落地转绿。
 * 覆盖 D-10-17：每页 <head> 直出 <title>、<meta name="description">、Open Graph 基础标签。
 *
 * @group site
 */

/**
 * 目标可观测信号：seed 已发布的 SiteCase，访问 GET /cases/{slug} 详情页，
 * HTTP 响应 body 含 <title>、<meta name="description">、<meta property="og:title">
 * （由 Plan 10-04 路由层 + Plan 10-05 视图渲染层落地转绿）
 */
it('案例详情页输出 SEO meta 标签', function () {
    $this->markTestIncomplete(
        '待 10-04/10-05 落地（D-10-17）：案例详情页响应应含 <title>、<meta name="description">、<meta property="og:title">'
    );
});
