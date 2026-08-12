<?php

namespace Filamentboot\FilamentbootSite\Settings;

use Filamentboot\Settings\UploadSettings;
use Illuminate\Support\Facades\Storage;
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
 * 双语时代的英文字段（company_name_en / phone_en / address_en /
 * seo_default_title_en / seo_default_description_en）已于
 * 2026_08_08_100001_drop_site_legacy_english_settings 删除。v1 只维护中文
 * 内容流，那 5 个键从英文入口下线之后再没被写过，也没有任何消费方。
 * 内容表侧的 21 个 `_en` 列在同一批一起清掉了。
 */
class SiteSettings extends Settings
{
    /**
     * 公司名称（中文）
     *
     * 默认空串：本类其余字段一律如此，这一个此前写死了首个接入站点的公司全名，
     * 下游 composer require 装完会**静默**得到别人的公司名——它会出现在页脚、
     * Organization 结构化数据与所有页面的标题后缀里，而站点设置页在没人打开过
     * 之前不会有任何提示。装上包不该凭空多出一个主体。
     *
     * 空串时前台走 defaultTitle() 的回退链（站点设置 → 公司名 → app.name）。
     */
    public string $company_name_zh = '';

    /** 联系电话 */
    public string $phone = '';

    /** 公司地址 */
    public string $address_zh = '';

    /** ICP 备案号 */
    public string $icp_number = '';

    /** 隐私政策链接（站点发布前必填项） */
    public string $privacy_url = '';

    /**
     * 页脚公司简介
     *
     * 与下面五个列表页导语同属 3.5 期 A 段补的「前台文案」一族：这 6 段话原本
     * 写死在各主题的 blade 里，前台每天都在显示，后台一个字都改不了。
     *
     * **默认空串，留空则前台整段不渲染**——不做视图侧兜底。有兜底就等于后台
     * 空着、前台仍有字，正是这一批改动要修的毛病。演示文案由 SiteDemoSeeder 填。
     */
    public string $footer_intro_zh = '';

    /** 案例列表页导语 */
    public string $list_intro_cases_zh = '';

    /** 方案列表页导语 */
    public string $list_intro_solutions_zh = '';

    /** 产品列表页导语 */
    public string $list_intro_products_zh = '';

    /** 套餐列表页导语 */
    public string $list_intro_packages_zh = '';

    /** 资讯列表页导语 */
    public string $list_intro_news_zh = '';

    /** SEO 默认标题 */
    public string $seo_default_title_zh = '';

    /** SEO 默认描述 */
    public string $seo_default_description_zh = '';

    /**
     * SEO 默认关键词
     *
     * 内容记录的 seo_keywords 留空时的回退值，也是首页与各列表页唯一的关键词来源
     * ——这两类页面没有对应记录，此前 keywords 恒为空串，meta 标签整条不输出。
     *
     * 留空即全站不输出 keywords，与此前行为一致。
     */
    public string $seo_default_keywords_zh = '';

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
     * 在线客服代码是否启用
     *
     * 与 live_chat_script 分开是为了「留着代码但先关掉」：换供应商、大促期间
     * 没人值守、或客服气泡挡了移动端底部操作条时，运营要能一键停掉，
     * 而不是把一大段脚本剪出去存在别处、回头再贴回来——那种操作迟早贴错。
     */
    public bool $live_chat_enabled = false;

    /**
     * 在线客服代码
     *
     * 输出在 </body> 前，风险与约束同 head_scripts（原样输出、不过 purifier、
     * 仅 manage_site_settings 可改）。独立成一个字段而不是让人塞进
     * body_end_scripts：客服代码是全站最长也最常被换的一段，
     * 与统计代码混在一个 textarea 里，改一处碰坏另一处只是时间问题。
     */
    public string $live_chat_script = '';

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
     * 搜狗站长平台验证串
     *
     * 单独说明它为什么值得占一个字段：搜狗不只是一个份额不大的搜索引擎，
     * 它同时是**腾讯元宝的检索源之一**。要让生成式引擎有机会引用本站，
     * 搜狗那边的收录就不能缺。
     */
    public string $sogou_verify_code = '';

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

    /**
     * 公司 LOGO 的可用地址
     */
    public function logoUrl(): ?string
    {
        return $this->mediaUrl($this->logo);
    }

    /**
     * 微信二维码的可用地址
     */
    public function wechatQrcodeUrl(): ?string
    {
        return $this->mediaUrl($this->wechat_qrcode);
    }

    /**
     * 全局 Open Graph 默认图的可用地址
     */
    public function ogDefaultImageUrl(): ?string
    {
        return $this->mediaUrl($this->og_default_image);
    }

    /**
     * 把媒体字段归一成可以直接放进 src / og:image 的地址
     *
     * 这三个字段的取值有两种来源，形状完全不同：后台 FileUpload 存的是相对磁盘根的
     * 路径（形如 01K2....png），而直接写库配置时填的是完整 URL（og_default_image
     * 一直是这么配的）。视图此前不加区分地塞进 src——拿到相对路径时浏览器按当前页面
     * 路径去解析，详情页上的 LOGO 请求就打到 /cases/01K2....png，404。
     *
     * 这个缺陷只在「有人从后台传过图」的站点上出现，装完包不配就永远看不见，
     * 属于典型的静默失效，因此归一放在设置类里由所有读取方共用，
     * 而不是让每个视图各写一遍判断。
     *
     * 空值统一返回 null，视图据此整块不渲染。
     */
    protected function mediaUrl(?string $value): ?string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        // 完整 URL、协议相对地址、站点根相对路径都已经能直接用
        if (preg_match('#^(?:[a-z][a-z0-9+.-]*:)?//#i', $value) === 1 || str_starts_with($value, '/')) {
            return $value;
        }

        return rescue(
            fn (): string => Storage::disk(app(UploadSettings::class)->default_disk)->url($value),
            null,
            report: false,
        );
    }
}
