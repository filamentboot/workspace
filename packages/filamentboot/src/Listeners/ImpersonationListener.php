<?php

namespace FilamentAdmin\Listeners;

use FilamentAdmin\Models\AdminUser;
use FilamentAdmin\Services\ActivityLogger;
use STS\FilamentImpersonate\Events\EnterImpersonation;
use STS\FilamentImpersonate\Events\LeaveImpersonation;

/**
 * 模拟登录事件监听器（FEAT-01 / D-32）
 *
 * 监听 stechstudio/filament-impersonate 的模拟开始/结束事件，
 * 通过 ActivityLogger 将操作写入主包统一审计日志（activity_log 表）。
 * 仅记录 admin guard 下的模拟操作（通过 instanceof AdminUser 过滤）。
 */
class ImpersonationListener
{
    /**
     * 构造注入操作日志服务
     *
     * @param  ActivityLogger  $logger  操作日志写入服务
     */
    public function __construct(protected ActivityLogger $logger) {}

    /**
     * 处理模拟开始事件
     *
     * 当超管发起模拟登录时，记录 impersonate.enter 日志。
     * 若发起者或被模拟用户非 AdminUser（如 web guard 普通用户），直接忽略不记录。
     *
     * @param  EnterImpersonation  $event  模拟开始事件（impersonator 为发起者，impersonated 为被模拟用户）
     */
    public function handleEnter(EnterImpersonation $event): void
    {
        if (! $event->impersonator instanceof AdminUser) {
            return;
        }

        if (! $event->impersonated instanceof AdminUser) {
            return;
        }

        $this->logger->log(
            causer: $event->impersonator,
            subject: $event->impersonated,
            action: 'impersonate.enter',
        );
    }

    /**
     * 处理模拟结束事件
     *
     * 当模拟会话结束时，记录 impersonate.leave 日志。
     * LeaveImpersonation::$impersonated 声明为 ?Authenticatable（可为 null），
     * 因此在访问前必须检查非空且为 AdminUser（防御性编程）。
     *
     * @param  LeaveImpersonation  $event  模拟结束事件（impersonated 可为 null）
     */
    public function handleLeave(LeaveImpersonation $event): void
    {
        if (! $event->impersonator instanceof AdminUser) {
            return;
        }

        if (! $event->impersonated instanceof AdminUser) {
            return;
        }

        $this->logger->log(
            causer: $event->impersonator,
            subject: $event->impersonated,
            action: 'impersonate.leave',
        );
    }
}
