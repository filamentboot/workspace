<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

/**
 * 初始化官网插件 Settings 分组（D-10-14 完整字段）
 *
 * 使用 Spatie migrator add() 逐字段添加，add() 对已存在键幂等（不覆盖既有值，T-10-01-03 防护）。
 * logo 与 wechat_qrcode 两个 Media 字段默认 null（nullable string，D-10-14）。
 * active_theme 默认 'decoration'（科技装修深色主题）。
 */
return new class extends SettingsMigration
{
    public function up(): void
    {
        // 公司名称（中文，含默认值）
        $this->migrator->add('site.company_name_zh', '湖北晴空妙享科技有限公司');

        // 公司名称（英文）
        $this->migrator->add('site.company_name_en', '');

        // 联系电话（中文）
        $this->migrator->add('site.phone', '');

        // 联系电话（英文）
        $this->migrator->add('site.phone_en', '');

        // 公司地址（中文）
        $this->migrator->add('site.address_zh', '');

        // 公司地址（英文）
        $this->migrator->add('site.address_en', '');

        // ICP 备案号
        $this->migrator->add('site.icp_number', '');

        // SEO 默认标题（中文）
        $this->migrator->add('site.seo_default_title_zh', '');

        // SEO 默认标题（英文）
        $this->migrator->add('site.seo_default_title_en', '');

        // SEO 默认描述（中文）
        $this->migrator->add('site.seo_default_description_zh', '');

        // SEO 默认描述（英文）
        $this->migrator->add('site.seo_default_description_en', '');

        // 当前激活主题（D-10-13，默认科技装修深色主题）
        $this->migrator->add('site.active_theme', 'decoration');

        // 公司 LOGO 媒体字段（D-10-14，存 URL 或媒体 ID，默认 null）
        $this->migrator->add('site.logo', null);

        // 微信二维码媒体字段（D-10-14，存 URL 或媒体 ID，默认 null）
        $this->migrator->add('site.wechat_qrcode', null);
    }
};
