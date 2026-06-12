<?php

use App\Services\PluginManager;
use Illuminate\Support\Facades\Http;

/**
 * Packagist 包名校验测试（PLUGIN-05）
 *
 * 覆盖：白名单直通、Packagist 404 阻断、semver 不满足阻断
 */

it('白名单来源的包直通 Packagist 校验', function () {
    Http::fake(); // 防止真实 HTTP 调用

    $manager = new PluginManager();

    // official_trusted 包名取自 config/official-market.php 已有条目
    $result = $manager->validatePackageName('awcodes/filament-tiptap-editor');

    expect($result)->toBeTrue();

    // 白名单直通，不调用 Packagist API
    Http::assertNothingSent();
});

it('Packagist API 返回 404 时阻断安装', function () {
    Http::fake([
        'repo.packagist.org/*' => Http::response(null, 404),
    ]);

    $manager = new PluginManager();

    // 非白名单包，Packagist 返回 404
    $result = $manager->validatePackageName('unknown-vendor/non-existent-package');

    expect($result)->toBeFalse();
});

it('Packagist 返回空版本列表时阻断安装', function () {
    // Packagist API 正常响应，但 packages 数组为空（包刚删除或无版本发布）
    // validatePackageName 判断 empty($versions) => false（WR-04 修复：删除原死代码块）
    Http::fake([
        'repo.packagist.org/p2/empty-vendor/empty-package.json' => Http::response([
            'packages' => [
                'empty-vendor/empty-package' => [],
            ],
        ], 200),
    ]);

    $manager = new PluginManager();
    $result  = $manager->validatePackageName('empty-vendor/empty-package');

    // 版本列表为空 => false（阻断安装）
    expect($result)->toBeFalse();
});

it('网络异常时阻断安装（安全优先）', function () {
    Http::fake([
        'repo.packagist.org/*' => fn () => throw new \Exception('Connection refused'),
    ]);

    $manager = new PluginManager();

    $result = $manager->validatePackageName('network-vendor/network-package');

    // 网络异常时阻断（安全优先，RESEARCH Pitfall 3）
    expect($result)->toBeFalse();
});
