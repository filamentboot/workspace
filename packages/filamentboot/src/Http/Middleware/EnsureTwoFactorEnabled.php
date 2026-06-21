<?php

namespace FilamentAdmin\Http\Middleware;

use Closure;
use FilamentAdmin\Settings\SecuritySettings;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * 强制双因素认证中间件（POLISH-02）
 *
 * 当 SecuritySettings.force_2fa=true 时，拦截未启用 2FA 的管理员访问后台页面，
 * 将其重定向到 2FA 设置页，同时始终放行登出/2FA设置/个人资料等页面（防锁死，D-06）。
 *
 * 设计决策（D-05）：注册在 Filament panel 的 authMiddleware 中（Authenticate 之后），
 * 仅对已登录用户生效，不建立独立的全局 HTTP 中间件。
 *
 * 超级管理员不豁免（D-04）：force_2fa=true 时所有用户均受拦截。
 */
class EnsureTwoFactorEnabled
{
    /**
     * 处理请求
     *
     * 逻辑优先级：
     * 1. 无用户（未认证）→ 直接放行（认证由 Authenticate 中间件负责）
     * 2. force_2fa=false → 放行
     * 3. 当前路由在放行清单中（登出/2FA设置/个人资料）→ 放行（防锁死）
     * 4. 用户已启用 2FA → 放行
     * 5. 以上条件均不满足 → 重定向到 2FA 设置页（含提示消息）
     *
     * @param  Request  $request  当前 HTTP 请求
     * @param  Closure(Request): Response  $next  下一个中间件
     */
    public function handle(Request $request, Closure $next): Response
    {
        // 步骤 1：无认证用户，交由 Authenticate 中间件处理
        $user = $request->user('admin');

        if ($user === null) {
            return $next($request);
        }

        // 步骤 2：未开启强制 2FA，直接放行
        if (! app(SecuritySettings::class)->force_2fa) {
            return $next($request);
        }

        // 步骤 3：当前路由在放行清单中（D-06：防止用户被锁死）
        // 放行：登出路由、2FA 相关页面（设置/Challenge/Recovery）、个人资料页
        if ($request->routeIs(
            'filament.admin.auth.logout',
            'filament.admin.auth.profile',
            'filament.admin.two-factor.*',
        )) {
            return $next($request);
        }

        // 步骤 4：用户已启用 2FA（two_factor_secret 非空且已确认），放行
        if (method_exists($user, 'hasEnabledTwoFactorAuthentication')
            && $user->hasEnabledTwoFactorAuthentication()
        ) {
            return $next($request);
        }

        // 步骤 5：未开 2FA，重定向到 2FA 设置页，附带提示消息
        return redirect()
            ->route('filament.admin.two-factor.setup')
            ->with('warning', __('请先启用双因素认证以继续使用后台系统。'));
    }
}
