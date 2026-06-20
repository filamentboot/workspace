<?php

/**
 * EnvironmentChecker 自检测试（MKTPLACE-04 / MKTPLACE-07）
 *
 * 覆盖场景：
 * 1. proc_open 被禁用时 selfCheck() 返回 ok=false，issues 非空
 * 2. COMPOSER_PATH 环境变量未设置且系统无 composer 时返回 ok=false
 * 3. vendor/ 目录无写权限时返回 ok=false，issues 非空
 * 4. 所有条件满足时返回 ok=true，composer_path 非空
 *
 * 威胁缓解：T-12-00-02 — selfCheck 永不抛异常，返回结果数组。
 * RESEARCH Pattern 5：'ok','composer_path','issues' 三键结果结构。
 */

it('proc_open 被禁用时 selfCheck 返回 ok=false（MKTPLACE-04）', function () {
    $this->markTestIncomplete('MKTPLACE-04: EnvironmentChecker::selfCheck() implemented in Wave 2');

    /** @var \FilamentAdmin\Services\EnvironmentChecker $checker */
    $checker = new \FilamentAdmin\Services\EnvironmentChecker(
        procOpenAvailable: false,  // 注入：proc_open 不可用
        composerPathOverride: null,
        vendorPathOverride: base_path('vendor'),
    );

    $result = $checker->selfCheck();

    expect($result)->toHaveKey('ok');
    expect($result['ok'])->toBeFalse();
    expect($result['issues'])->not->toBeEmpty();

    // 确认错误信息含 proc_open 关键字
    $combined = implode(' ', $result['issues']);
    expect($combined)->toContain('proc_open');
});

it('COMPOSER_PATH 未设置且 which composer 无结果时 selfCheck 返回 ok=false（MKTPLACE-07）', function () {
    $this->markTestIncomplete('MKTPLACE-07: EnvironmentChecker::selfCheck() implemented in Wave 2');

    /** @var \FilamentAdmin\Services\EnvironmentChecker $checker */
    $checker = new \FilamentAdmin\Services\EnvironmentChecker(
        procOpenAvailable: true,
        composerPathOverride: '',  // 注入：无 composer 路径
        vendorPathOverride: base_path('vendor'),
    );

    $result = $checker->selfCheck();

    expect($result['ok'])->toBeFalse();
    expect($result['composer_path'])->toBeNull();

    $combined = implode(' ', $result['issues']);
    expect($combined)->toContain('Composer');
});

it('vendor/ 目录无写权限时 selfCheck 返回 ok=false（MKTPLACE-07）', function () {
    $this->markTestIncomplete('MKTPLACE-07: EnvironmentChecker vendor writable check implemented in Wave 2');

    /** @var \FilamentAdmin\Services\EnvironmentChecker $checker */
    $checker = new \FilamentAdmin\Services\EnvironmentChecker(
        procOpenAvailable: true,
        composerPathOverride: '/usr/local/bin/composer',
        vendorPathOverride: '/nonexistent-unwritable-path',  // 注入：不可写路径
    );

    $result = $checker->selfCheck();

    expect($result['ok'])->toBeFalse();
    expect($result['issues'])->not->toBeEmpty();

    $combined = implode(' ', $result['issues']);
    expect($combined)->toContain('vendor');
});

it('所有条件满足时 selfCheck 返回 ok=true（MKTPLACE-04/07）', function () {
    $this->markTestIncomplete('MKTPLACE-04/07: EnvironmentChecker ok=true path implemented in Wave 2');

    /** @var \FilamentAdmin\Services\EnvironmentChecker $checker */
    $checker = new \FilamentAdmin\Services\EnvironmentChecker(
        procOpenAvailable: true,
        composerPathOverride: '/usr/local/bin/composer',
        vendorPathOverride: base_path('vendor'),
    );

    $result = $checker->selfCheck();

    expect($result)->toHaveKeys(['ok', 'composer_path', 'issues']);
    expect($result['ok'])->toBeTrue();
    expect($result['composer_path'])->not->toBeNull();
    expect($result['issues'])->toBeEmpty();
});
