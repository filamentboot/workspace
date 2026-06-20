<?php

/**
 * 插件版本兼容性三态判定测试（MKTPLACE-05 / D-12-15）
 *
 * 覆盖场景（全三态）：
 * 1. 有效约束 '^5.0' 与当前 v5.x => 'compatible'（绿标）
 * 2. 不可能满足的约束 '^3.0' => 'incompatible'（红标）
 * 3. null 约束 => 'unknown'（黄标，不硬拦截，D-12-15）
 * 4. 空字符串约束 => 'unknown'
 * 5. 格式错误约束 => 'unknown'（捕获异常，D-12-15 边界处理）
 *
 * 威胁缓解：T-12-00-02 — Semver 比对纯 PHP 计算，无网络调用。
 * RESEARCH Pattern 4：Semver::satisfies 三态逻辑。
 */

use FilamentAdmin\Services\PluginCompatibility;

it('约束 ^5.0 与当前 Filament v5.x 比对结果为 compatible（MKTPLACE-05）', function () {
    $service = app(PluginCompatibility::class);

    $result = $service->checkFilamentCompatibility('^5.0');

    expect($result)->toBe('compatible');
});

it('不可能满足的约束 ^3.0 结果为 incompatible（MKTPLACE-05）', function () {
    $service = app(PluginCompatibility::class);

    $result = $service->checkFilamentCompatibility('^3.0');

    expect($result)->toBe('incompatible');
});

it('null 约束结果为 unknown（MKTPLACE-05 / D-12-15 未声明黄标）', function () {
    $service = app(PluginCompatibility::class);

    $result = $service->checkFilamentCompatibility(null);

    expect($result)->toBe('unknown');
});

it('空字符串约束结果为 unknown（MKTPLACE-05）', function () {
    $service = app(PluginCompatibility::class);

    $result = $service->checkFilamentCompatibility('');

    expect($result)->toBe('unknown');
});

it('格式错误约束结果为 unknown（不抛异常，D-12-15 边界处理）', function () {
    $service = app(PluginCompatibility::class);

    // 格式无效的约束字符串（非 semver 语法）
    $result = $service->checkFilamentCompatibility('not-a-valid-semver-constraint!!!');

    // 格式错误捕获后返回 unknown（不抛异常，异常优先原则的例外：容错设计）
    expect($result)->toBe('unknown');
});
