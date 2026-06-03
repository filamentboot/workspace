<?php

namespace FilamentAdmin\Enums;

/**
 * 管理员状态枚举
 */
enum AdminUserStatus: string
{
    case Active   = 'active';
    case Disabled = 'disabled';

    public function label(): string
    {
        return match ($this) {
            self::Active   => '启用',
            self::Disabled => '禁用',
        };
    }
}
