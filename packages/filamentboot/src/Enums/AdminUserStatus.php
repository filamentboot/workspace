<?php

namespace Filamentboot\Enums;

/**
 * 管理员状态枚举
 */
enum AdminUserStatus: string
{
    case Active   = 'active';
    case Disabled = 'disabled';
    /** 登录失败达阈值后由系统自动锁定（L-04） */
    case Locked   = 'locked';

    public function label(): string
    {
        return match ($this) {
            self::Active   => '启用',
            self::Disabled => '禁用',
            self::Locked   => '锁定',
        };
    }
}
