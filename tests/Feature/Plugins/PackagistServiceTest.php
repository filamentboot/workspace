<?php

use App\Services\PackagistService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * PackagistService 测试（MKTPLACE-08）
 *
 * 覆盖场景：
 * 1. searchFilamentPlugins 使用 Http::fake 正常返回 results 数组
 * 2. getPackageConstraint 使用 p2 端点 Http::fake 返回 filament/filament 约束字符串
 * 3. HTTP 非 200 响应时返回空兜底结构（{results:[], total:0, next:null}）
 * 4. 搜索结果被 Cache 缓存，二次调用不重复发 HTTP 请求
 *
 * 威胁缓解：T-12-00-02 — Http::fake；CI 绝不命中真实 packagist.org。
 * RESEARCH Pattern 3：Packagist 搜索 + p2 端点。
 */

it('searchFilamentPlugins 返回 results 数组（MKTPLACE-08）', function () {
    Cache::flush();

    Http::fake([
        'packagist.org/search.json*' => Http::response(fakePackagistSearch(), 200),
    ]);

    /** @var PackagistService $service */
    $service = app(PackagistService::class);
    $result  = $service->searchFilamentPlugins();

    expect($result)->toHaveKey('results');
    expect($result['results'])->toBeArray();
    expect($result['results'])->not->toBeEmpty();
    expect($result['total'])->toBeGreaterThan(0);
});

it('getPackageConstraint 通过 p2 端点返回 filament/filament 约束（MKTPLACE-08）', function () {
    Cache::flush();

    Http::fake([
        'repo.packagist.org/p2/bezhansalleh/filament-shield.json' => Http::response([
            'packages' => [
                'bezhansalleh/filament-shield' => [
                    [
                        'version'            => '4.0.0',
                        'version_normalized' => '4.0.0.0',
                        'require'            => [
                            'filament/filament' => '^5.0',
                            'php'               => '^8.3',
                        ],
                    ],
                ],
            ],
        ], 200),
    ]);

    /** @var PackagistService $service */
    $service = app(PackagistService::class);
    $result  = $service->getPackageConstraint('bezhansalleh/filament-shield');

    expect($result)->toBe('^5.0');
});

it('HTTP 非 200 时 searchFilamentPlugins 返回空兜底结构（MKTPLACE-08）', function () {
    Cache::flush();

    Http::fake([
        'packagist.org/search.json*' => Http::response('Service Unavailable', 503),
    ]);

    /** @var PackagistService $service */
    $service = app(PackagistService::class);
    $result  = $service->searchFilamentPlugins();

    // 兜底结构：{results:[], total:0, next:null}
    expect($result)->toHaveKey('results');
    expect($result['results'])->toBeEmpty();
    expect($result['total'])->toBe(0);
    expect($result['next'])->toBeNull();
});

it('searchFilamentPlugins 结果被缓存，相同参数二次调用不再发 HTTP（MKTPLACE-08）', function () {
    Cache::flush();

    Http::fake([
        'packagist.org/search.json*' => Http::response(fakePackagistSearch(), 200),
    ]);

    /** @var PackagistService $service */
    $service = app(PackagistService::class);

    // 首次调用
    $service->searchFilamentPlugins(1, 15);

    // 第二次调用（相同参数）
    $service->searchFilamentPlugins(1, 15);

    // 只发出 1 次 HTTP 请求（第二次命中缓存）
    Http::assertSentCount(1);
});
