<?php

namespace Filamentboot\FilamentbootSite\Settings;

use Spatie\LaravelSettings\Settings;

/**
 * 官网全局设置 Settings 类（D-10-14 完整字段）
 *
 * 存储公司基本信息、SEO 默认值、主题选择及 Media Library 媒体字段。
 * logo、wechat_qrcode 与 og_default_image 存储媒体文件 URL（nullable string），
 * 确保 settings 表列为标量、可直查（D-10-04 直观可查原则）。
 *
 * active_theme 控制前台渲染使用的主题目录，取值必须存在于
 * config('filamentboot-site.themes') 白名单中。
 *
 * 英文字段（*_en）为 CMS v1 之前的双语实现遗留。v1 只维护中文内容流，
 * 后台表单与前台渲染均已移除英文入口；此处保留属性仅为兼容既有数据，
 * 删除属性需要额外的 Spatie Settings 迁移且无实际收益。
 */
class SiteSettings extends Settings
{
    /** 公司名称（中文） */
    public string $company_name_zh = '湖北晴空妙享科技有限公司';

    /** 公司名称（英文，遗留字段，v1 不再使用） */
    public string $company_name_en = '';

    /** 联系电话 */
    public string $phone = '';

    /** 联系电话（英文，遗留字段，v1 不再使用） */
    public string $phone_en = '';

    /** 公司地址 */
    public string $address_zh = '';

    /** 公司地址（英文，遗留字段，v1 不再使用） */
    public string $address_en = '';

    /** ICP 备案号 */
    public string $icp_number = '';

    /** 隐私政策链接（站点发布前必填项） */
    public string $privacy_url = '';

    /** SEO 默认标题 */
    public string $seo_default_title_zh = '';

    /** SEO 默认标题（英文，遗留字段，v1 不再使用） */
    public string $seo_default_title_en = '';

    /** SEO 默认描述 */
    public string $seo_default_description_zh = '';

    /** SEO 默认描述（英文，遗留字段，v1 不再使用） */
    public string $seo_default_description_en = '';

    /**
     * 当前激活主题标识（D-10-13）
     *
     * 取值来自 config('filamentboot-site.themes') 的键。
     * 前台 ServiceProvider 根据此值确定主题目录，非法值会被强制回退到默认主题。
     */
    public string $active_theme = 'decoration';

    /**
     * 公司 LOGO 媒体字段（D-10-14）
     *
     * 存储媒体文件 URL（nullable string），
     * 上传组件使用默认磁盘（UploadSettings.default_disk，SITE-04 跨切）。
     */
    public ?string $logo = null;

    /**
     * 微信二维码媒体字段（D-10-14）
     *
     * 存储媒体文件 URL（nullable string），
     * 上传组件使用默认磁盘（UploadSettings.default_disk，SITE-04 跨切）。
     */
    public ?string $wechat_qrcode = null;

    /**
     * 全局 Open Graph 默认图
     *
     * 内容页无自身封面时的 og:image 回退。未配置（null）时视图不输出 og:image，
     * 避免指向不存在的文件造成社交平台抓取 404。
     */
    public ?string $og_default_image = null;

    /**
     * 新询盘通知收件人（A2）
     *
     * 多个地址用英文逗号分隔，留空即关闭通知。营销站线索响应速度是转化的
     * 头号变量，不配置就只能靠人主动登后台刷新。
     */
    public string $notify_emails = '';

    /**
     * 百度统计站点 ID（A3）
     *
     * 只填 ID，脚本由固定模板生成，不给填写方写 JS 的机会。
     */
    public string $baidu_tongji_id = '';

    /**
     * Google Analytics 4 衡量 ID（A3）
     *
     * 形如 G-XXXXXXXXXX，脚本由固定模板生成。
     */
    public string $ga_measurement_id = '';

    /**
     * 自定义 <head> 代码块（A3）
     *
     * ⚠️ 原样输出到前台且**不过 purifier**（过滤会破坏脚本），等于开放前台
     * 任意 JS 执行能力。仅 manage_site_settings 权限可修改，变更写操作日志。
     * 这是有意开放的例外：由受信管理员配置且有权限与审计兜底，
     * 与「富文本一律走白名单过滤」面向内容编辑的场景不同。
     */
    public string $head_scripts = '';

    /**
     * 自定义 </body> 前代码块（A3）
     *
     * 风险与约束同 head_scripts。
     */
    public string $body_end_scripts = '';

    /**
     * 百度站长平台验证串（B4）
     *
     * 输出为 <meta name="baidu-site-verification">。做成设置项而非改模板，
     * 换验证方式或换站点时不必发版。值会进 content 属性，输出前校验字符集。
     */
    public string $baidu_verify_code = '';

    /**
     * Google Search Console 验证串（B4）
     *
     * 输出为 <meta name="google-site-verification">，约束同上。
     */
    public string $google_verify_code = '';

    /**
     * Bing 网站管理员工具验证串（B4）
     *
     * 输出为 <meta name="msvalidate.01">，约束同上。
     */
    public string $bing_verify_code = '';

    /**
     * 百度主动推送准入密钥（B4）
     *
     * 在百度搜索资源平台「普通收录 - API 提交」页获取。留空即关闭推送：
     * 推送服务直接返回，不排队也不报错——多数下游装了包并不会用百度推送。
     */
    public string $baidu_push_token = '';

    /**
     * 百度主动推送的站点域名（B4）
     *
     * 必须与站长平台登记的站点完全一致（含 www 与否），否则接口返回
     * not_same_site。留空时退回 config('app.url') 的主机名。
     */
    public string $baidu_push_site = '';

    /**
     * Settings 分组名
     */
    public static function group(): string
    {
        return 'site';
    }
}
