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
 *
 * ## 两条 2026-08-07 补上的行为
 *
 * **一、不再覆盖控制器显式声明的缓存策略。** 此前是无条件 `set()`，于是
 * sitemap.xml / robots.txt 里写的 `max-age=3600` 从来没生效过——代码写着一小时，
 * 实际发出去的一直是十分钟。这类「写了但被静默改掉」比数值本身错更麻烦：
 * 读代码的人会以为它成立。
 *
 * **二、判定为不可缓存时，主动剥掉响应上已有的 public 缓存头。** 此前是直接返回、
 * 不动响应头，于是「带 Set-Cookie 且控制器自己打了 public」这个组合会被原样放行
 * ——**正是本类存在的意义要防的那件事**。当时无害只是因为凑巧没有这种路由，
 * 属于埋着的雷，不是安全的设计。
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
            $this->revokePublicCaching($response);

            return $response;
        }

        // 控制器已显式声明公共缓存策略（如 sitemap 的一小时）就照它的来。
        // 到这一步已经确认没有 Set-Cookie，尊重它是安全的。
        if ($this->hasExplicitPublicCaching($response)) {
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

    /**
     * 控制器是否已经自己声明了公共缓存
     *
     * 同时要求 public 与 max-age 两个指令：Symfony 在没人设置时给的默认是
     * `no-cache, private`，只判其中一个会把默认值误当成显式声明。
     */
    protected function hasExplicitPublicCaching(Response $response): bool
    {
        return $response->headers->hasCacheControlDirective('public')
            && $response->headers->hasCacheControlDirective('max-age');
    }

    /**
     * 撤销响应上已有的 public 缓存声明
     *
     * 只在判定为不可缓存时调用。带会话 Cookie 的响应一旦标成 public，
     * 共享缓存会把一个访客的会话发给另一个——这是必须堵死的，
     * 不能指望「控制器不会这么写」。
     */
    protected function revokePublicCaching(Response $response): void
    {
        if (! $response->headers->hasCacheControlDirective('public')) {
            return;
        }

        $response->headers->set('Cache-Control', 'private, no-store');
    }
}
