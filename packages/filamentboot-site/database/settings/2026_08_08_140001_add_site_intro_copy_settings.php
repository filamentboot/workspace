<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

/**
 * 页脚简介与五个列表页导语改由站点设置驱动（3.5 期 A 段修复六）
 *
 * 这 6 段文案此前写死在各主题的 blade 里——**前台每天都在显示，后台一个字都改不了**。
 * 运营要改一句导语只能提需求改代码，属于「前台显示的后台看不见」那一类对应性缺口，
 * 与同期把导航兜底数组落库（SiteFrontMenuSeeder）是同一件事的两面。
 *
 * ## 为什么默认是空串而不是把现有文案写成默认值
 *
 * 与 SiteSettings::$company_name_zh 同一个理由：下游 composer require 装完包
 * 不该凭空得到别人写的营销文案。而且这 6 段文案是**装修主题口径**的
 * （「智能家居」「全屋智能」），设置本身却跨主题共用——写成默认值等于让
 * software 主题的站点也默认顶着装修站的话术。
 *
 * 于是取值改成：**留空则整段不渲染**（视图侧 `@if`），演示文案由 SiteDemoSeeder
 * 负责填。空设置对应空白页面，后台所见即前台所显，不再有隐形兜底。
 *
 * ## 与 seo_default_description_zh 的区别
 *
 * 那个是给搜索引擎看的 meta，这 6 个是页面上的正文。两者内容取向不同
 * （一个塞关键词、一个讲人话），不合并成一个字段。
 */
return new class extends SettingsMigration
{
    /**
     * 新增的 6 个键
     *
     * @var list<string>
     */
    private const NAMES = [
        'site.footer_intro_zh',
        'site.list_intro_cases_zh',
        'site.list_intro_solutions_zh',
        'site.list_intro_products_zh',
        'site.list_intro_packages_zh',
        'site.list_intro_news_zh',
    ];

    public function up(): void
    {
        foreach (self::NAMES as $name) {
            $this->migrator->add($name, '');
        }
    }

    /**
     * 回滚删掉这 6 个键
     *
     * 与删列那条迁移不同，这里的 down() 是真能还原的：属性与键同进同退，
     * 回滚后 SiteSettings 类里对应的 6 个属性也要一起去掉，否则映射器
     * 取不到值会抛 MissingSettings，后台设置页白屏。
     *
     * 代价是运营填过的文案会丢——回滚一条「新增设置项」的迁移本来就是这个含义。
     */
    public function down(): void
    {
        foreach (self::NAMES as $name) {
            $this->migrator->deleteIfExists($name);
        }
    }
};
