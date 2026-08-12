<?php

namespace Filamentboot\FilamentbootSite\Support;

use Filamentboot\FilamentbootSite\SiteServiceProvider;

/**
 * 各内容类型的公开侧栏目名（七期批次 2）
 *
 * 面包屑、SEO 列表标题、站点地图 sections()/llms.txt 分组、shared/ 视图的
 * alt 文案与图片占位符标签共用同一份取值——此前这些位置各自硬编码，且
 * 完全不感知 active_theme：software 主题上线后，面包屑仍显示「装修案例」
 * 「全屋智能套餐」「智能家居资讯」这类 decoration 专属措辞，文不对题
 * （实测确认：本仓库 active_theme 当前就是 software）。
 *
 * 按主题分岔的写法照抄 4 个 Filament Resource 的 getModelLabel()
 * （`SiteServiceProvider::resolveActiveTheme() === 'software' ? A : B`，
 * 五期批次 9 已验证过这个模式），software 分支的具体措辞取自 nav/footer
 * 组件兜底数组与 SiteFrontMenuSeeder::softwareMenus() 里早已在用的文案，
 * 不是新造的营销词。
 *
 * **不覆盖后台侧。** 4 个 Resource 的 getModelLabel() 用的是更短的管理
 * 语境词（如「案例」而非「客户案例」），是刻意与这里分开的另一套词表，
 * 服务不同的读者——见那几个方法上五期批次 9 的注释，这里不重复它们。
 */
final class ContentTypeLabels
{
    public static function case(): string
    {
        return self::isSoftware() ? '客户案例' : '装修案例';
    }

    public static function solution(): string
    {
        return self::isSoftware() ? '应用场景' : '智能方案';
    }

    public static function package(): string
    {
        return self::isSoftware() ? '版本与定价' : '全屋套餐';
    }

    public static function product(): string
    {
        return self::isSoftware() ? '产品与模块' : '智能产品';
    }

    public static function news(): string
    {
        return self::isSoftware() ? '资讯' : '资讯中心';
    }

    /**
     * 服务城市——城市页是 decoration/corporate 专属能力，software 主题不建
     * SiteCityPage（见 SiteFrontMenuSeeder::softwareMenus() 的注释），
     * 这条路由与视图在 software 下实际不会渲染，无需分岔。
     */
    public static function city(): string
    {
        return '服务城市';
    }

    private static function isSoftware(): bool
    {
        return SiteServiceProvider::resolveActiveTheme() === 'software';
    }
}
