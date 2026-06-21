<?php

namespace Filamentboot\FilamentbootSite\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * 语言环境切换中间件（双语 D-10-09）
 *
 * 当请求路径以 'en' 开头时（即 /en/ 英文前缀路由），
 * 将应用语言环境切换为英文，否则保持默认中文。
 */
class SetLocaleMiddleware
{
    /**
     * 处理传入请求
     *
     * @param Request $request 当前 HTTP 请求
     * @param Closure(Request): Response $next 下一个中间件
     * @return Response
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (str_starts_with($request->path(), 'en')) {
            app()->setLocale('en');
        }

        return $next($request);
    }
}
