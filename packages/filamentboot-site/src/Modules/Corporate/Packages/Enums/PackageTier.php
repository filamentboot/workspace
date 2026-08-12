<?php

namespace Filamentboot\FilamentbootSite\Modules\Corporate\Packages\Enums;

/**
 * 套餐档位枚举
 *
 * 对应 site_packages.tier 列存储值。三档并排比是套餐页的核心版式，
 * 所以档位既是筛选维度，也是**排序键**——同一户型下要按「定制 → 舒适 → 豪华」
 * 稳定排列，不能靠 sort 字段人工维护。
 *
 * `weight()` 就是那个排序键。放在枚举里而不是写进查询：两套主题的列表页、
 * 详情页的「同户型其它档位」、以及日后的对比表都要用同一套顺序。
 */
enum PackageTier: string
{
    /** 定制款 */
    case CUSTOM = 'custom';

    /** 舒适款 */
    case COMFORT = 'comfort';

    /** 豪华款 */
    case DELUXE = 'deluxe';

    /**
     * 获取枚举对应的中文标签
     */
    public function label(): string
    {
        return match ($this) {
            self::CUSTOM  => '定制款',
            self::COMFORT => '舒适款',
            self::DELUXE  => '豪华款',
        };
    }

    /**
     * 一句话说明这一档是给谁的
     *
     * 用在对比表的档位表头下面。只讲选择依据，不讲具体配了什么——
     * 具体配置在每条套餐自己的包含清单里，那才是有出处的部分。
     */
    public function summary(): string
    {
        return match ($this) {
            self::CUSTOM  => '按需要挑几件，先把最想解决的问题解决掉',
            self::COMFORT => '常用场景一次做齐，日常使用的舒适度都覆盖到',
            self::DELUXE  => '全屋联动做满，包含影音、安防与更完整的传感网络',
        };
    }

    /**
     * 排序权重（小的排前面）
     *
     * 与 `cases()` 的声明顺序一致。显式给数字而不是用 `array_search`：
     * 它要拼进 SQL 的 `ORDER BY FIELD(...)`，数字比位置查找直观。
     */
    public function weight(): int
    {
        return match ($this) {
            self::CUSTOM  => 1,
            self::COMFORT => 2,
            self::DELUXE  => 3,
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

    /**
     * 按 weight 升序排好的枚举列表
     *
     * 前台三档并排比直接遍历它，不用在视图里再排一次。
     *
     * @return list<self>
     */
    public static function ordered(): array
    {
        $cases = self::cases();

        usort($cases, static fn (self $a, self $b): int => $a->weight() <=> $b->weight());

        return $cases;
    }
}
