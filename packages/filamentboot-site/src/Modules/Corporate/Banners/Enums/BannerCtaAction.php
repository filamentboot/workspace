<?php

namespace Filamentboot\FilamentbootSite\Modules\Corporate\Banners\Enums;

/**
 * 幻灯片按钮行为枚举
 *
 * 对应 site_banners.cta_action 列存储值。
 *
 * 为什么要这一列，而不是「cta_url 留空就弹询盘面板」
 * ------------------------------------------------
 * 首页原来那个 hero 的主 CTA「预约咨询」触发的是询盘面板
 * （`$store.contactPanel.show()`），不是跳链接。幻灯片替掉 hero 之后这个
 * 转化入口不能丢，所以按钮至少要能表达两种行为。
 *
 * 用隐式约定（URL 空 = 弹面板）能省一列，但编辑在后台看不出「我留空是想
 * 弹面板，还是我忘了填」——两种意图在数据上长得一样，只能靠 helperText 补救。
 * 显式枚举多一个下拉，换来的是意图可见、可校验、可在表单里联动隐藏 URL 输入框。
 */
enum BannerCtaAction: string
{
    /** 跳转链接（读 cta_url） */
    case LINK = 'link';

    /** 打开询盘面板（不读 cta_url） */
    case INQUIRY = 'inquiry';

    /** 不显示按钮 */
    case NONE = 'none';

    /**
     * 获取枚举对应的中文标签
     */
    public function label(): string
    {
        return match ($this) {
            self::LINK    => '跳转链接',
            self::INQUIRY => '打开预约咨询面板',
            self::NONE    => '不显示按钮',
        };
    }
}
