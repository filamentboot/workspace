<?php

namespace App\Listeners;

use App\Models\LoginLog;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;

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

        LoginLog::create([
            'admin_user_id'  => $event->user?->id,
            'username'       => $this->extractUsername($event),
            'status'         => $status,
            'ip_address'     => request()->ip() ?? '127.0.0.1',
            'user_agent'     => request()->userAgent(),
            'failure_reason' => $status === 'failed'
                ? $this->determineFailureReason($event)
                : null,
        ]);
    }

    /**
     * 提取用户名（支持 username、email、login 三种字段）
     */
    private function extractUsername(Login|Failed $event): ?string
    {
        if ($event->user) {
            // 成功登录时，优先使用 username
            return $event->user->username ?? $event->user->email ?? null;
        }

        // 失败登录时，从凭据中提取
        return $event->credentials['username']
            ?? $event->credentials['email']
            ?? $event->credentials['login']
            ?? null;
    }

    /**
     * 确定登录失败原因
     */
    private function determineFailureReason(Failed $event): string
    {
        return 'invalid_credentials';
    }
}
