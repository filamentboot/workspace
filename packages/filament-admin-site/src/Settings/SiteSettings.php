<?php

namespace LaravelStack\FilamentAdminSite\Settings;

use Spatie\LaravelSettings\Settings;

/**
 * 官网全局设置 Settings 类（D-10-14 完整字段）
 *
 * 存储公司基本信息、SEO 默认值、主题选择及 Media Library 媒体字段。
 * logo 和 wechat_qrcode 存储媒体文件 URL 或媒体 ID（nullable string），
 * 确保 settings 表列为标量、可直查（D-10-04 直观可查原则）。
 *
 * active_theme 控制前台渲染使用的主题目录（'decoration' | 'tech-product'）。
 */
class SiteSettings extends Settings
{
    /** 公司名称（中文） */
    public string $company_name_zh = '湖北晴空妙享科技有限公司';

    /** 公司名称（英文） */
    public string $company_name_en = '';

    /** 联系电话（中文） */
    public string $phone = '';

    /** 联系电话（英文） */
    public string $phone_en = '';

    /** 公司地址（中文） */
    public string $address_zh = '';

    /** 公司地址（英文） */
    public string $address_en = '';

    /** ICP 备案号 */
    public string $icp_number = '';

    /** SEO 默认标题（中文） */
    public string $seo_default_title_zh = '';

    /** SEO 默认标题（英文） */
    public string $seo_default_title_en = '';

    /** SEO 默认描述（中文） */
    public string $seo_default_description_zh = '';

    /** SEO 默认描述（英文） */
    public string $seo_default_description_en = '';

    /**
     * 当前激活主题标识（D-10-13）
     *
     * 可选值：'decoration'（科技装修深色主题）| 'tech-product'（科技产品浅色主题）
     * 前台 ServiceProvider 根据此值加载对应 Blade 目录。
     */
    public string $active_theme = 'decoration';

    /**
     * 公司 LOGO 媒体字段（D-10-14）
     *
     * 存储媒体文件 URL 或媒体 ID（nullable string），
     * 上传组件使用默认磁盘（UploadSettings.default_disk，SITE-04 跨切）。
     * nullable 避免 Spatie Settings 强制非空。
     */
    public ?string $logo = null;

    /**
     * 微信二维码媒体字段（D-10-14）
     *
     * 存储媒体文件 URL 或媒体 ID（nullable string），
     * 上传组件使用默认磁盘（UploadSettings.default_disk，SITE-04 跨切）。
     * nullable 避免 Spatie Settings 强制非空。
     */
    public ?string $wechat_qrcode = null;

    /**
     * Settings 分组名
     */
    public static function group(): string
    {
        return 'site';
    }
}
