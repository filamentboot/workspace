<?php

namespace Filamentboot\FilamentbootSite\Cms\Routing;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * 公开页缓存响应头（#29）
 *
 * 只给「安全方法 + 200 + 没有 Set-Cookie」的响应打 `public, max-age`。三个条件缺一不可：
 *
 * - 非 GET/HEAD 不缓存，这是显然的。
 * - 非 200（含 404 与重定向）不缓存：一次误发布导致的 404 被 CDN 缓存住，
 *   等于把事故延长到缓存过期。
 * - **带 Set-Cookie 的响应绝不打 public**。这是最关键的一条：一旦某个页面意外起了
 *   session（宿主加了中间件、某个视图调了 csrf_token()、包内又混进 Livewire），
 *   把带会话 Cookie 的响应标成公共可缓存，共享缓存就会把 A 的会话发给 B。
 *   宁可退回不缓存，也不能漏这个。
 *
 * TTL 读 config，默认 600 秒。内容改动后旧页面最多再存活这么久——官网内容改动频率低，
 * 十分钟换取整页缓存是划算的；要更实时就把它调小或配 CDN 主动刷新。
 */
class SiteCacheHeaders
{
    /**
     * 处理请求
     *
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! $this->shouldCache($request, $response)) {
            return $response;
        }

        $ttl = (int) config('filamentboot-site.cache.public_max_age', 600);

        if ($ttl <= 0) {
            return $response;
        }

        $response->headers->set('Cache-Control', 'public, max-age='.$ttl);

        return $response;
    }

    /**
     * 这个响应能不能被公共缓存
     */
    protected function shouldCache(Request $request, Response $response): bool
    {
        if (! $request->isMethodCacheable()) {
            return false;
        }

        if ($response->getStatusCode() !== 200) {
            return false;
        }

        // 起了会话就退回不缓存——见类注释里的理由
        return $response->headers->getCookies() === [];
    }
}
