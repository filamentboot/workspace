<?php

namespace Filamentboot\FilamentbootSite\Modules\Corporate\Packages\Enums;

/**
 * 套餐户型枚举
 *
 * 对应 site_packages.house_layout 列存储值，也是套餐列表页的筛选维度。
 *
 * ## 为什么不复用 Cases\Enums\HouseType
 *
 * `HouseType` 是「几居室」（一居 / 二居 / 三居 / 别墅 / 复式 / 其他），
 * 表达不了**三室一厅和三室两厅的区别**——而这恰恰是装修者自己在用的说法，
 * 也是套餐报价的分档依据：两个厅意味着多一套灯光回路、多一组开关面板、
 * 多一个网关覆盖点，价格和配置清单都不一样。
 *
 * 硬把「厅」塞进 `HouseType` 会污染案例那边已经在用的筛选（案例按居室数分就够了），
 * 所以另起一个枚举，`site_cases` 完全不动。
 *
 * 顺序按「小 → 大」排，`values()` 直接就是前台筛选条与后台下拉的展示顺序。
 */
enum HouseLayout: string
{
    /** 一室一厅 */
    case ONE_ONE = 'one_one';

    /** 两室一厅 */
    case TWO_ONE = 'two_one';

    /** 三室一厅 */
    case THREE_ONE = 'three_one';

    /** 三室两厅 */
    case THREE_TWO = 'three_two';

    /** 四室一厅 */
    case FOUR_ONE = 'four_one';

    /** 四室两厅 */
    case FOUR_TWO = 'four_two';

    /** 别墅 / 复式 */
    case VILLA = 'villa';

    /**
     * 获取枚举对应的中文标签
     */
    public function label(): string
    {
        return match ($this) {
            self::ONE_ONE   => '一室一厅',
            self::TWO_ONE   => '两室一厅',
            self::THREE_ONE => '三室一厅',
            self::THREE_TWO => '三室两厅',
            self::FOUR_ONE  => '四室一厅',
            self::FOUR_TWO  => '四室两厅',
            self::VILLA     => '别墅 / 复式',
        };
    }

    /**
     * 后台下拉与前台筛选共用的 value => label 映射
     *
     * @return array<string, string>
     */
    public static function options(): array
    {
        $options = [];

        foreach (self::cases() as $case) {
            $options[$case->value] = $case->label();
        }

        return $options;
    }
}
