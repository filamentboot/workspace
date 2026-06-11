<?php

use App\Services\MarketplaceService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * 市场服务测试（PLUGIN-07）
 *
 * 覆盖：fetchIndex 命中缓存不写 plugins 表、HTTP 失败兜底本地 config
 */

it('fetchIndex 命中缓存时不写 plugins 表', function () {
    Cache::flush();

    // 预热缓存
    Cache::put('market.index', [['slug' => 'cached-plugin']], 300);

    Http::fake(); // 防止真实 HTTP 调用

    $service = new MarketplaceService();
    $result  = $service->fetchIndex();

    // 命中缓存，不触发 HTTP
    Http::assertNothingSent();

    // 浏览不写 plugins 表（D-06-06/07）
    expect($result)->not->toBeEmpty();
    $this->assertDatabaseCount('plugins', 0);
});

it('fetchIndex HTTP 回源时仍不写 plugins 表', function () {
    Cache::flush();

    Http::fake([
        '*' => Http::response([
            'entries' => [
                ['slug' => 'remote-plugin-1'],
                ['slug' => 'remote-plugin-2'],
            ],
        ], 200),
    ]);

    $service = new MarketplaceService();
    $result  = $service->fetchIndex();

    // 返回远程数据
    expect($result)->toHaveCount(2);
    expect($result[0]['slug'])->toBe('remote-plugin-1');

    // 浏览不写 plugins 表（D-06-06/07）
    $this->assertDatabaseCount('plugins', 0);
});

it('fetchIndex HTTP 失败时返回本地兜底配置', function () {
    Cache::flush();

    Http::fake([
        '*' => Http::response('Server Error', 500),
    ]);

    $service = new MarketplaceService();
    $result  = $service->fetchIndex();

    // 兜底 config('official-market.entries') 有 6 条
    expect($result)->toHaveCount(6);

    // 浏览不写 plugins 表
    $this->assertDatabaseCount('plugins', 0);
});
