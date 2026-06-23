<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Packagist 社区市场服务
 *
 * 从 packagist.org 实时检索 filament 标签的社区插件，走 HTTP 缓存（TTL=300s）。
 * 与 MarketplaceService（官方精选）并列，是三标签目录（Plan 04）的社区数据来源。
 *
 * 威胁缓解：
 * - T-12-03-01: HTTPS + mailto User-Agent（Packagist 官方要求）
 * - T-12-03-03: Cache::remember(300) + 空兜底，Packagist 宕机不阻塞 UI
 *
 * RESEARCH §Pattern 3：Packagist 搜索 + p2 端点；Pitfall 3：稳定版过滤。
 */
class PackagistService
{
    /** Packagist 搜索基础 URL */
    private const SEARCH_URL = 'https://packagist.org/search.json';

    /** Packagist p2 元数据基础 URL */
    private const P2_BASE_URL = 'https://repo.packagist.org/p2';

    /**
     * 搜索 filament 标签的社区插件（分页）
     *
     * 成功响应结构：{ results: [{name, description, url, repository, downloads, favers}], total: int, next: ?string }
     * 非 200 或异常时返回空兜底（不写入缓存，下次请求仍会回源）。
     *
     * @return array{results: list<array<string, mixed>>, total: int, next: string|null}
     */
    public function searchFilamentPlugins(int $page = 1, int $perPage = 15): array
    {
        $cacheKey = "packagist.search.filament.p{$page}.pp{$perPage}";
        $fallback = ['results' => [], 'total' => 0, 'next' => null];

        try {
            return Cache::remember($cacheKey, 300, function () use ($page, $perPage, $fallback): array {
                $response = Http::withHeaders(['User-Agent' => $this->userAgent()])
                    ->retry(2, 100)
                    ->timeout(10)
                    ->get(self::SEARCH_URL, [
                        'tags'     => 'filament',
                        'per_page' => $perPage,
                        'page'     => $page,
                    ]);

                if (! $response->ok()) {
                    // 非 200 不写入缓存（throw 逃出 Cache::remember 闭包）
                    throw new \RuntimeException('Packagist non-200: '.$response->status());
                }

                return $response->json() ?? $fallback;
            });
        } catch (\Throwable) {
            return $fallback;
        }
    }

    /**
     * 获取指定包声明的 filament/filament 约束（取最新稳定版）
     *
     * 过滤 dev/alpha/beta 版本（RESEARCH Pitfall 3 — 不稳定版本排在前面）。
     * 非 200、无稳定版或无效包名时返回 null。
     *
     * WR-01：防御性校验 $packageName 格式，避免 list-destructure 崩溃。
     */
    public function getPackageConstraint(string $packageName): ?string
    {
        // WR-01：包名必须包含斜杠，否则 explode 返回单元素数组，导致 PHP Warning
        if (! str_contains($packageName, '/')) {
            return null;
        }

        [$vendor, $pkg] = explode('/', $packageName, 2);
        $cacheKey       = "packagist.p2.{$vendor}.{$pkg}";

        try {
            return Cache::remember($cacheKey, 300, function () use ($vendor, $pkg, $packageName): ?string {
                $response = Http::withHeaders(['User-Agent' => $this->userAgent()])
                    ->retry(2, 100)
                    ->timeout(10)
                    ->get(self::P2_BASE_URL."/{$vendor}/{$pkg}.json");

                if (! $response->ok()) {
                    throw new \RuntimeException('Packagist p2 non-200: '.$response->status());
                }

                $versions = $response->json("packages.{$packageName}", []);

                // 取最新稳定版：过滤 dev/alpha/beta（Pitfall 3）
                foreach ($versions as $v) {
                    $normalized = $v['version_normalized'] ?? '';
                    $stability  = $v['version'] ?? '';
                    if (str_contains($normalized, 'dev') ||
                        str_contains($stability, 'alpha') ||
                        str_contains($stability, 'beta') ||
                        str_contains($stability, 'RC') ||
                        str_starts_with($normalized, '9999999')) {
                        continue;
                    }

                    return $v['require']['filament/filament'] ?? null;
                }

                return null;
            });
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * 构造 Packagist 官方要求的 User-Agent（含 mailto 联系方式）
     */
    private function userAgent(): string
    {
        $mailto = config('mail.from.address', 'admin@example.com');

        return "filamentboot/1.0 (mailto:{$mailto})";
    }
}
