<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

/**
 * 补充官网发布前必备的 SEO 与联系方式设置项
 *
 * og_default_image：全局 Open Graph 默认图。此前 seo-meta 组件硬编码
 * asset('img/og-default.jpg')，该文件并不存在，线上 og:image 始终 404。
 * 改为可配置字段，未配置时视图不输出 og:image。
 *
 * privacy_url：隐私政策链接，属于站点发布前必填项（页脚合规入口）。
 *
 * add() 对已存在键幂等，不覆盖既有值（T-10-01-03 防护）。
 */
return new class extends SettingsMigration
{
    public function up(): void
    {
        // 全局 Open Graph 默认图（存 URL，默认 null 表示不输出 og:image）
        $this->migrator->add('site.og_default_image', null);

        // 隐私政策链接
        $this->migrator->add('site.privacy_url', '');
    }
};
