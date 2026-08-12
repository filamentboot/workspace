<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

/**
 * 新增 SEO 默认关键词设置项
 *
 * 六类内容各自有 seo_keywords，但首页与四个列表页没有对应记录，
 * 控制器给它们的 keywords 写死成空串，meta 关键词标签从来没在这些页面出现过
 * ——恰恰是这几页承载站点主词。补一个全局默认值把回退链接上。
 *
 * 默认空串：装上包不该凭空多出一条 meta 标签，行为与此前保持一致。
 *
 * add() 对已存在键幂等，不覆盖既有值（T-10-01-03 防护）。
 */
return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('site.seo_default_keywords_zh', '');
    }
};
