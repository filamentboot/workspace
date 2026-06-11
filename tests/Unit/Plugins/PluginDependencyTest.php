<?php

/**
 * Wave 0 占位：插件依赖关系测试（PLUGIN-06）
 *
 * 待 Plan 02 Task 2 实现后转为真实断言：
 * - plugin.json compatibility 不兼容时启用被阻断并给出警告
 * - compatibility 不兼容时禁用时给出警告（影响范围提示）
 */

it('compatibility 声明不兼容时启用插件被阻断', function () {
    $this->markTestIncomplete('Wave 0 占位：待 Plan 02 实现 — 断言 compatibility 字段不满足当前 Laravel/Filament 版本时 enable() 操作被阻断（PLUGIN-06）');
});

it('compatibility 声明不兼容时给出明确警告信息', function () {
    $this->markTestIncomplete('Wave 0 占位：待 Plan 02 实现 — 断言不兼容时返回/抛出含版本约束详情的警告信息（PLUGIN-06）');
});
