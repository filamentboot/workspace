<?php

namespace Filamentboot\FilamentbootSite\Modules\Corporate\Banners\Enums;

/**
 * 幻灯片投放位置枚举
 *
 * 对应 site_banners.position 列存储值。
 *
 * 首页用全屏版式（banner-hero），四个列表页用矮横幅版式（banner-strip）——
 * 版式由视图决定、不由本枚举携带：同一个位置将来换版式不该动数据。
 */
enum BannerPosition: string
{
    /** 首页顶部（全屏主视觉，无幻灯片时降级回单图 hero） */
    case HOME_TOP = 'home_top';

    /** 案例列表页顶部 */
    case CASE_INDEX_TOP = 'case_index_top';

    /** 方案列表页顶部 */
    case SOLUTION_INDEX_TOP = 'solution_index_top';

    /** 产品列表页顶部 */
    case PRODUCT_INDEX_TOP = 'product_index_top';

    /** 资讯列表页顶部 */
    case NEWS_INDEX_TOP = 'news_index_top';

    /**
     * 获取枚举对应的中文标签
     */
    public function label(): string
    {
        return match ($this) {
            self::HOME_TOP           => '首页顶部（全屏）',
            self::CASE_INDEX_TOP     => '案例列表页顶部',
            self::SOLUTION_INDEX_TOP => '方案列表页顶部',
            self::PRODUCT_INDEX_TOP  => '产品列表页顶部',
            self::NEWS_INDEX_TOP     => '资讯列表页顶部',
        };
    }
}
