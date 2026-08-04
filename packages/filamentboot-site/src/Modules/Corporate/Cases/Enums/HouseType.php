<?php

namespace Filamentboot\FilamentbootSite\Modules\Corporate\Cases\Enums;

/**
 * 户型枚举
 *
 * 对应 site_cases.house_type 列存储值。
 */
enum HouseType: string
{
    /** 一居室 */
    case ONE_BEDROOM = 'one_bedroom';

    /** 二居室 */
    case TWO_BEDROOM = 'two_bedroom';

    /** 三居室 */
    case THREE_BEDROOM = 'three_bedroom';

    /** 别墅 */
    case VILLA = 'villa';

    /** 复式 */
    case DUPLEX = 'duplex';

    /** 其他 */
    case OTHER = 'other';

    /**
     * 获取枚举对应的中文标签
     */
    public function label(): string
    {
        return match ($this) {
            self::ONE_BEDROOM   => '一居室',
            self::TWO_BEDROOM   => '二居室',
            self::THREE_BEDROOM => '三居室',
            self::VILLA         => '别墅',
            self::DUPLEX        => '复式',
            self::OTHER         => '其他',
        };
    }
}
