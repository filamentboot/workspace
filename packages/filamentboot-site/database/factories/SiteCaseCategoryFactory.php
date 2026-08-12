<?php

namespace Filamentboot\FilamentbootSite\Database\Factories;

use Filamentboot\FilamentbootSite\Modules\Corporate\Cases\Models\SiteCaseCategory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * 装修案例分类工厂
 *
 * @extends Factory<SiteCaseCategory>
 */
class SiteCaseCategoryFactory extends Factory
{
    /** @var class-string<SiteCaseCategory> */
    protected $model = SiteCaseCategory::class;

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
            'slug'      => Str::slug($nameZh).'-'.$this->faker->unique()->numberBetween(1, 9999),
            'parent_id' => null,
            'sort'      => $this->faker->numberBetween(0, 100),
        ];
    }
}
