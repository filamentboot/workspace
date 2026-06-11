<?php

/**
 * Wave 0 占位：插件面板注册测试（PLUGIN-01）
 *
 * 待 Plan 04 Task 2 实现后转为真实断言：
 * - is_enabled=true 的插件其 plugin_class 被 AdminPanelProvider 接入 Panel
 * - is_enabled=false 的插件不接入 Panel
 */

it('已启用的插件 plugin_class 被接入 AdminPanel', function () {
    $this->markTestIncomplete('Wave 0 占位：待 Plan 04 实现 — 断言 is_enabled=true 的 Plugin 其 plugin_class 被 AdminPanelProvider 注册到 Panel');
});

it('未启用的插件不接入 AdminPanel', function () {
    $this->markTestIncomplete('Wave 0 占位：待 Plan 04 实现 — 断言 is_enabled=false 的 Plugin 其 plugin_class 不被注册到 Panel');
});
