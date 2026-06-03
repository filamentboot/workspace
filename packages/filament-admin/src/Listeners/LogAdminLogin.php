<?php

namespace FilamentAdmin\Listeners;

use FilamentAdmin\Models\AdminUser;
use FilamentAdmin\Models\LoginLog;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;

/**
 * 管理员登录日志监听器
 *
 * 登录成功时写回最后登录时间和 IP，重置失败次数；
 * 登录失败时若能找到对应用户则累加失败次数。
 */
class LogAdminLogin
{
    /**
     * 处理登录成功或失败事件，仅记录 admin guard 的操作
     */
    public function handle(Login|Failed $event): void
    {
        // 仅处理 admin guard 的事件
        if ($event->guard !== 'admin') {
            return;
        }

        $status = $event instanceof Login ? 'success' : 'failed';
        $ip     = request()->ip() ?? '127.0.0.1';

        LoginLog::create([
            'admin_user_id'  => $event->user instanceof AdminUser ? $event->user->id : null,
            'username'       => $this->extractLoginIdentifier($event),
            'status'         => $status,
            'ip_address'     => $ip,
            'user_agent'     => request()->userAgent(),
            'failure_reason' => $status === 'failed' ? 'invalid_credentials' : null,
        ]);

        // 登录成功：更新最后登录时间和 IP，重置失败次数
        if ($status === 'success' && $event->user instanceof AdminUser) {
            $event->user->updateQuietly([
                'last_login_at'  => now(),
                'last_login_ip'  => $ip,
                'login_failures' => 0,
            ]);
        }

        // 登录失败：若能找到用户则累加失败次数
        if ($status === 'failed') {
            $this->incrementLoginFailures($event->credentials ?? []);
        }
    }

    /**
     * 提取登录标识（账号或邮箱）
     */
    private function extractLoginIdentifier(Login|Failed $event): ?string
    {
        if ($event->user instanceof AdminUser) {
            // 成功登录时，优先使用 account
            return $event->user->account ?? $event->user->email ?? null;
        }

        // 失败登录时，从凭据中提取（兼容 account、email、login 三种字段名）
        return $event->credentials['account']
            ?? $event->credentials['email']
            ?? $event->credentials['login']
            ?? null;
    }

    /**
     * 尝试为对应用户累加登录失败次数
     *
     * @param  array<string, mixed>  $credentials
     */
    private function incrementLoginFailures(array $credentials): void
    {
        $identifier = $credentials['account']
            ?? $credentials['email']
            ?? $credentials['login']
            ?? null;

        if (blank($identifier)) {
            return;
        }

        $user = AdminUser::query()
            ->where('account', $identifier)
            ->orWhere('email', $identifier)
            ->first();

        $user?->increment('login_failures');
    }
}
