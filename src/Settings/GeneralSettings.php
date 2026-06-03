<?php

namespace FilamentAdmin\Settings;

use Spatie\LaravelSettings\Settings;

/**
 * 基础配置 Settings 类
 *
 * 存储站点名称、后台标题、备案信息、版权文字等基础配置。
 */
class GeneralSettings extends Settings
{
    /** 站点名称 */
    public string $site_name = 'FilamentAdmin';

    /** 后台标题 */
    public string $admin_title = '系统管理后台';

    /** 备案号 */
    public string $icp_number = '';

    /** 底部版权信息 */
    public string $copyright = '';

    /**
     * Settings 分组名
     */
    public static function group(): string
    {
        return 'general';
    }
}
