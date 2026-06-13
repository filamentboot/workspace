<?php

namespace LaravelStack\FilamentAdminSite\Enums;

/**
 * 询盘消息状态枚举
 *
 * 对应 site_contact_messages.status 列存储值。
 * 状态流转：unread → contacted → closed。
 */
enum ContactMessageStatus: string
{
    /** 未读 */
    case UNREAD = 'unread';

    /** 已联系 */
    case CONTACTED = 'contacted';

    /** 已关闭 */
    case CLOSED = 'closed';

    /**
     * 获取枚举对应的中文标签
     */
    public function label(): string
    {
        return match ($this) {
            self::UNREAD     => '未读',
            self::CONTACTED  => '已联系',
            self::CLOSED     => '已关闭',
        };
    }
}
