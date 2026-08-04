<?php

namespace Filamentboot\FilamentbootSite\Http\Middleware;

use Closure;
use Filamentboot\FilamentbootSite\Support\SafeUrl;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

/**
 * 旧 URL 301 重定向中间件（#18）
 *
 * 页面 slug 变更后旧 URL 必须能跳到新地址，否则已被搜索引擎收录的链接
 * 和外部引用一起变成 404。
 *
 * **为什么是全局中间件**：旧 URL 已经匹配不到任何路由，路由中间件根本跑不到。
 * 另外两条路都不通——
 *   Route::fallback()：Laravel 只认一个 fallback，包一注册就顶掉宿主自己的
 *     404 处理，对要发 Packagist 的包是硬伤。
 *   接管 404 异常渲染：配置在宿主的 bootstrap/app.php，包无法自己挂钩，
 *     要求下游手工加一行，违反「composer require 即可用」。
 * 由 SiteServiceProvider 在**插件启用时** pushMiddleware() 注册。
 *
 * 全局中间件意味着宿主的每个请求都要过一次这里，所以第一件事是挂载路径早退：
 * 不落在官网范围内直接放行，宿主自己的路由不为此付出一次 DB 查询。
 */
class SiteRedirectMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        // 只处理 GET/HEAD：POST 到旧地址跳转会丢请求体，跳过去也没有意义
        if (! $request->isMethodSafe()) {
            return $next($request);
        }

        // 请求路径不落在官网挂载范围（prefix / root / domain）内直接放行
        if (! $this->isSitePath($request)) {
            return $next($request);
        }

        $from = static::normalizePath($request->path());

        if ($from === '') {
            return $next($request);
        }

        $redirect = DB::table('site_redirects')
            ->where('from_path', $from)
            ->first(['id', 'to_path', 'status_code']);

        if ($redirect === null) {
            return $next($request);
        }

        $target = $this->targetUrl((string) $redirect->to_path);

        // to_path 被 scheme 白名单拦下（历史脏数据或误填 javascript:）时当作没配，
        // 让请求继续走正常路由——跳到一个不安全地址比 404 严重得多
        if ($target === null) {
            return $next($request);
        }

        // hits 用单条 UPDATE 不走模型：省一次 SELECT 和全部模型事件，
        // 而且这条路径在每个 404 前都会跑，越轻越好
        DB::table('site_redirects')->where('id', $redirect->id)->increment('hits');

        $status = (int) $redirect->status_code;

        return redirect($target, in_array($status, [301, 302, 307, 308], true) ? $status : 301);
    }

    /**
     * 归一化路径：去前后斜杠、去查询串
     *
     * 入库与查询都走这个方法，避免 `/old`、`old/`、`/old?utm_source=x`
     * 三种写法对不上同一条记录。
     */
    public static function normalizePath(string $path): string
    {
        $path = explode('?', $path, 2)[0];
        $path = explode('#', $path, 2)[0];

        return trim($path, '/');
    }

    /**
     * 解析跳转目标
     *
     * to_path 既可以是站内路径（/new-about）也可以是完整外链。
     * 站内路径补上前导斜杠再过 SafeUrl，与 #13 共用同一份 scheme 白名单。
     */
    protected function targetUrl(string $toPath): ?string
    {
        $toPath = trim($toPath);

        if ($toPath === '') {
            return null;
        }

        // 无 scheme 且不以 / 或 # 开头 → 视为站内相对路径，补上前导斜杠。
        //
        // ⚠️ 判据必须是「有没有 scheme」而不是「有没有 ://」：
        // javascript:alert(1) 不含 ://，按 :// 判会被补成 /javascript:alert(1)，
        // 于是变成一个"站内路径"直接通过 SafeUrl——伪协议就这样绕了过去。
        $hasScheme = is_string(parse_url($toPath, PHP_URL_SCHEME));

        if (! $hasScheme && ! str_starts_with($toPath, '/') && ! str_starts_with($toPath, '#')) {
            $toPath = '/'.$toPath;
        }

        return SafeUrl::sanitize($toPath);
    }

    /**
     * 请求是否落在官网挂载范围内
     *
     * 三种挂载模式各自的判据：
     *   root   官网接管根路径 → 全部路径都算（但保留 slug 除外，见下）
     *   prefix 官网挂在 /{prefix} 下 → 只有该前缀下的路径算
     *   domain 官网绑定独立域名 → 只有该域名的请求算
     *
     * root 模式下额外排除 reserved_slugs：/admin、/livewire、/storage 这些
     * 是宿主与框架自己的路径，让它们也过一次重定向查询纯属浪费——
     * 后台每个 Livewire 轮询都会打到 /livewire/update。
     */
    protected function isSitePath(Request $request): bool
    {
        /** @var array<string, mixed> $config */
        $config = (array) config('filamentboot-site.route', []);

        $mode = $config['mode'] ?? 'prefix';

        if ($mode === 'domain') {
            $domain = $config['domain'] ?? null;

            return is_string($domain) && $domain !== '' && $request->getHost() === $domain;
        }

        $path = static::normalizePath($request->path());

        if ($mode === 'root') {
            $firstSegment = explode('/', $path)[0];

            return ! in_array($firstSegment, (array) ($config['reserved_slugs'] ?? []), true);
        }

        $prefix = trim((string) ($config['prefix'] ?? 'site'), '/');

        return $prefix !== '' && ($path === $prefix || str_starts_with($path, $prefix.'/'));
    }
}
