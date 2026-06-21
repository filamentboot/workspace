<?php

namespace Filamentboot\FilamentbootSite\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Filamentboot\FilamentbootSite\Models\SiteProductCategory;

/**
 * 智能产品分类工厂
 *
 * @extends Factory<SiteProductCategory>
 */
class SiteProductCategoryFactory extends Factory
{
    /** @var class-string<SiteProductCategory> */
    protected $model = SiteProductCategory::class;

    /**
     * 定义模型默认状态
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $nameZh = $this->faker->words(2, true);

        return [
            'name_zh'   => $nameZh,
            'name_en'   => $this->faker->words(2, true),
            'slug'      => Str::slug($nameZh) . '-' . $this->faker->unique()->numberBetween(1, 9999),
            'parent_id' => null,
            'sort'      => $this->faker->numberBetween(0, 100),
        ];
    }
}
