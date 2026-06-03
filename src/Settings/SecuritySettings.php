<?php

namespace FilamentAdmin\Settings;

use Spatie\LaravelSettings\Settings;

/**
 * 安全配置 Settings 类
 *
 * 存储登录限制次数、锁定时长、是否强制 2FA 等安全配置。
 */
class SecuritySettings extends Settings
{
    /** 登录失败限制次数（0 = 不限制） */
    public int $login_throttle_max_attempts = 5;

    /** 登录失败锁定时长（分钟） */
    public int $login_throttle_decay_minutes = 15;

    /** 是否强制启用双因素认证 */
    public bool $force_2fa = false;

    /**
     * Settings 分组名
     */
    public static function group(): string
    {
        return 'security';
    }
}
