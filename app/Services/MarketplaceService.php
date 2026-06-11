<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * 官方市场服务
 *
 * 从远程 index.json 拉取市场清单，走 HTTP 缓存（TTL=300s），浏览不写 MySQL。
 * （D-06-06/07 数据源以远程 index.json 为主 + 本地兜底，浏览不写 plugins 表）
 */
class MarketplaceService
{
    /**
     * 获取官方市场清单（HTTP 缓存 TTL=300s，失败兜底本地 config）
     *
     * @return array<int, array<string, mixed>>
     */
    public function fetchIndex(): array
    {
        $url = config('plugin-platform.official_market_index_url');

        return Cache::remember('market.index', 300, function () use ($url): array {
            try {
                return Http::retry(2, 100)
                    ->timeout(10)
                    ->get($url)
                    ->json('entries', []);
            } catch (\Throwable) {
                // 网络失败时兜底本地配置（D-06-08 MITM 缓解：网络失败兜底本地 config）
                return config('official-market.entries', []);
            }
        });
    }
}
