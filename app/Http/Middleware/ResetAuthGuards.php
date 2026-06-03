<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Auth\RequestGuard;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * 每次 API 请求前重置认证 Guard 状态
 *
 * 防止在长生命周期进程（如 Octane/Swoole）或测试环境中，
 * Guard 缓存的用户实例跨请求污染。
 */
class ResetAuthGuards
{
    /**
     * 处理传入请求
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // 仅重置 sanctum RequestGuard 缓存的用户，防止跨请求用户状态污染
        // 使用 instanceof 收窄类型，forgetUser() 由 GuardHelpers trait 提供
        $guard = Auth::guard('sanctum');
        if ($guard instanceof RequestGuard) {
            $guard->forgetUser();
        }

        return $next($request);
    }
}
