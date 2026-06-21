<?php

namespace FilamentAdmin\Settings;

use Spatie\LaravelSettings\Settings;

/**
 * 日志配置 Settings 类
 *
 * 存储操作日志和登录日志的保留天数配置。
 */
class LogSettings extends Settings
{
    /** 操作日志保留天数（0 = 永久保留） */
    public int $activity_log_retention_days = 90;

    /** 登录日志保留天数（0 = 永久保留） */
    public int $login_log_retention_days = 180;

    /**
     * Settings 分组名
     */
    public static function group(): string
    {
        return 'log';
    }
}
