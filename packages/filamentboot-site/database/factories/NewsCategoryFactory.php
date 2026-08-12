<?php

namespace Filamentboot\FilamentbootSite\Database\Factories;

use Filamentboot\FilamentbootSite\Modules\News\Models\NewsCategory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * 资讯分类工厂
 *
 * @extends Factory<NewsCategory>
 */
class NewsCategoryFactory extends Factory
{
    /** @var class-string<NewsCategory> */
    protected $model = NewsCategory::class;

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
            'slug'      => Str::slug($nameZh).'-'.$this->faker->unique()->numberBetween(1, 99999),
            'parent_id' => null,
            'sort'      => $this->faker->numberBetween(0, 100),
        ];
    }
}
