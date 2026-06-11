<?php

/**
 * Wave 0 占位：市场服务测试（PLUGIN-07）
 *
 * 待 Plan 02 Task 1 实现后转为真实断言：
 * - fetchIndex 命中 Cache 时不写 plugins 表（assertDatabaseCount plugins=0）
 * - fetchIndex 走 HTTP 回源时仍不写 plugins 表（仅写 Cache）
 */

it('fetchIndex 命中缓存时不写 plugins 表', function () {
    $this->markTestIncomplete('Wave 0 占位：待 Plan 02 实现 — 断言 MarketplaceService::fetchIndex Cache 命中时 assertDatabaseCount(plugins, 0)（PLUGIN-07）');
});

it('fetchIndex 走 HTTP 回源时仍不写 plugins 表', function () {
    $this->markTestIncomplete('Wave 0 占位：待 Plan 02 实现 — 断言 MarketplaceService::fetchIndex HTTP 回源后结果写入 Cache 而非 plugins 表（PLUGIN-07）');
});
