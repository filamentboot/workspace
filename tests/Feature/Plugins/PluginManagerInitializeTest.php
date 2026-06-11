<?php

/**
 * Wave 0 占位：插件管理器初始化测试（PLUGIN-02 / PLUGIN-03）
 *
 * 待 Plan 02 Task 2 实现后转为真实断言：
 * - PluginManager::initialize 同步执行 migrate/publish/seed 并写 init_status
 * - 失败时保留 init_log，init_status = 'failed'
 * - 重试整体幂等（连续两次调用不抛异常）
 */

it('PluginManager initialize 同步执行后 init_status 变为 done', function () {
    $this->markTestIncomplete('Wave 0 占位：待 Plan 02 实现 — 断言 PluginManager::initialize 同步执行 migrate/publish/seed 并将 init_status 写为 done（PLUGIN-02）');
});

it('初始化失败时 init_log 保留错误信息且 init_status 为 failed', function () {
    $this->markTestIncomplete('Wave 0 占位：待 Plan 02 实现 — 断言初始化异常时 init_log 写入错误详情，init_status = failed（PLUGIN-03）');
});

it('重复调用 initialize 整体幂等不抛异常', function () {
    $this->markTestIncomplete('Wave 0 占位：待 Plan 02 实现 — 断言连续两次 initialize 调用均不抛异常，最终状态收敛（PLUGIN-03 重试幂等）');
});
