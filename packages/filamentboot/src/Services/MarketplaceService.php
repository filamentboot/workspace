<?php

namespace Filamentboot\Services;

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

        try {
            // 仅成功响应写入缓存；4xx/5xx 或网络故障走 catch 分支，不污染缓存
            return Cache::remember('market.index', 300, function () use ($url): array {
                return Http::retry(2, 100)
                    ->timeout(10)
                    ->throw()           // 4xx/5xx 进入 catch 分支（CR-01 修复）
                    ->get($url)
                    ->json('entries', []);
            });
        } catch (\Throwable) {
            // 网络/HTTP 错误：返回本地兜底配置，不写入缓存，下次请求仍会回源
            // （D-06-08 MITM 缓解：兜底本地 config，避免缓存污染）
            return config('official-market.entries', []);
        }
    }
}
