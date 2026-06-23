<?php

use App\Services\PluginManager;
use Filamentboot\Models\Plugin;

/**
 * 插件依赖关系测试（PLUGIN-06）
 *
 * Phase 12（D-12-09）: compatibility 字段已从 plugins 表移除；
 * 兼容性比对迁移至 Packagist p2 端点（MKTPLACE-05，Plan 02）。
 * 本文件测试 checkDependencies 在 Phase 12 语义下的行为：
 * 无本地 compatibility 字段时返回空数组（无阻塞），启用操作不被阻断。
 */
it('checkDependencies Phase 12 语义：无本地 compatibility 字段时返回空数组', function () {
    $plugin = Plugin::factory()->create([
        'is_enabled' => false,
    ]);

    $manager = new PluginManager;
    $issues  = $manager->checkDependencies($plugin);

    // Phase 12: compatibility 已移至 Packagist p2，本地无约束时返回空
    expect($issues)->toBeEmpty();
});

it('checkDependencies 返回空数组时 enable 不抛异常', function () {
    $plugin = Plugin::factory()->create([
        'is_enabled' => false,
    ]);

    $manager = new PluginManager;

    // Phase 12: 无 compatibility 字段，enable 不被阻断
    expect(fn () => $manager->enable($plugin))->not->toThrow(RuntimeException::class);
    expect($plugin->refresh()->is_enabled)->toBeTrue();
});
