<?php

namespace Filamentboot\FilamentbootSite\Database\Factories;

use Filamentboot\FilamentbootSite\Modules\Corporate\Cities\Models\SiteRegion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * 行政区划工厂
 *
 * 默认造一个**地级**区划（城市页最常挂的那一级）。省级与县级用状态方法。
 *
 * ## 代码走自增计数器，不用随机数
 *
 * `code` 上有唯一索引、`(parent_code, slug)` 上还有一个联合唯一索引，
 * 随机数在一次测试里造几十条就有概率撞——而撞了的表现是唯一约束报错，
 * **偶发、只在某些随机种子下出现**，最难查的那一类。
 *
 * 生成的代码不遵循 GB/T 2260（`1xxxxx` 省 / `2xxxxx` 地 / `3xxxxx` 县），
 * 这不影响任何东西：模型只把 code 当成一个不透明的六位字符串，
 * 区划代码的结构只在导入命令的校验里出现，而那条路径不经过工厂。
 *
 * @extends Factory<SiteRegion>
 */
class SiteRegionFactory extends Factory
{
    /** @var class-string<SiteRegion> */
    protected $model = SiteRegion::class;

    /** 三级共用的自增序号，保证代码与 slug 互不相撞 */
    protected static int $sequence = 0;

    /**
     * 定义模型默认状态（地级）
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $seq = ++static::$sequence;

        return [
            'code'        => sprintf('2%05d', $seq),
            'parent_code' => '',
            'level'       => SiteRegion::LEVEL_CITY,
            'name'        => '测试市'.$seq,
            'short_name'  => '测试'.$seq,
            'slug'        => 'city-'.$seq,
            'sort'        => 0,
        ];
    }

    /**
     * 省级
     */
    public function province(): static
    {
        return $this->state(function (): array {
            $seq = ++static::$sequence;

            return [
                'code'        => sprintf('1%05d', $seq),
                'parent_code' => '',
                'level'       => SiteRegion::LEVEL_PROVINCE,
                'name'        => '测试省'.$seq,
                'short_name'  => '测省'.$seq,
                'slug'        => 'province-'.$seq,
            ];
        });
    }

    /**
     * 县级（没有简称也没有 slug，不建页）
     */
    public function county(): static
    {
        return $this->state(function (): array {
            $seq = ++static::$sequence;

            return [
                'code'       => sprintf('3%05d', $seq),
                'level'      => SiteRegion::LEVEL_COUNTY,
                'name'       => '测试区'.$seq,
                'short_name' => null,
                'slug'       => null,
            ];
        });
    }

    /**
     * 挂到某个上级区划下
     */
    public function childOf(SiteRegion $parent): static
    {
        return $this->state(['parent_code' => $parent->code]);
    }
}
