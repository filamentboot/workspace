<?php

/**
 * Wave 0 占位：Packagist 包名校验测试（PLUGIN-05）
 *
 * 待 Plan 02 Task 2 实现后转为真实断言：
 * - 白名单 source（official_trusted）的包直通校验
 * - Packagist API 返回 404 时阻断安装并提示包不存在
 * - semver 版本约束不满足时阻断安装
 */

it('白名单来源的包直通 Packagist 校验', function () {
    $this->markTestIncomplete('Wave 0 占位：待 Plan 02 实现 — 断言 source=official_trusted 的包跳过 Packagist API 检查直通（PLUGIN-05）');
});

it('Packagist API 返回 404 时阻断安装', function () {
    $this->markTestIncomplete('Wave 0 占位：待 Plan 02 实现 — 断言 Packagist API 404 响应时抛出异常并阻断安装流程（PLUGIN-05）');
});

it('semver 版本约束不满足时阻断安装', function () {
    $this->markTestIncomplete('Wave 0 占位：待 Plan 02 实现 — 断言版本约束不满足（installed_version 与 requires 不兼容）时安装被阻断（PLUGIN-05）');
});
