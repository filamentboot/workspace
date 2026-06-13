<?php

/**
 * 前台主题视图切换测试桩（SiteThemeViewTest）
 *
 * Wave 0 安全网测试，由 Plan 10-05（视图/主题渲染）落地转绿。
 * 覆盖 D-10-13：SiteSettings.active_theme 控制前台加载的 Blade 目录。
 * 可观测信号：SITE-03 主题切换后视图解析路径变更。
 *
 * @group site
 */

/**
 * 目标可观测信号：设置 active_theme='decoration' 后前台视图解析 themes/decoration 目录；
 * 切换为 'tech-product' 后解析 themes/tech-product 目录（SITE-03 主题切换可观测）
 * （由 Plan 10-05 registerFrontend 中动态加载主题视图目录后落地转绿）
 */
it('active_theme 切换后视图解析到对应主题目录', function () {
    $this->markTestIncomplete(
        '待 10-05 落地（SITE-03）：active_theme=\'decoration\' 应解析 themes/decoration；切换 \'tech-product\' 应解析 themes/tech-product'
    );
});
