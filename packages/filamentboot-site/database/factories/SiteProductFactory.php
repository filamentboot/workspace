<?php

namespace Filamentboot\FilamentbootSite\Database\Factories;

use Filamentboot\FilamentbootSite\Modules\Corporate\Products\Models\SiteProduct;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * 智能产品工厂
 *
 * @extends Factory<SiteProduct>
 */
class SiteProductFactory extends Factory
{
    /** @var class-string<SiteProduct> */
    protected $model = SiteProduct::class;

    /**
     * 定义模型默认状态（默认为已发布）
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $titleZh = $this->faker->words(3, true);

        return [
            'title_zh'        => $titleZh,
            'title_en'        => $this->faker->words(3, true),
            'slug'            => Str::slug($titleZh).'-'.$this->faker->unique()->numberBetween(1, 99999),
            'description_zh'  => $this->faker->paragraph(),
            'description_en'  => $this->faker->paragraph(),
            'price'           => $this->faker->randomFloat(2, 100, 50000),
            'brand'           => $this->faker->company(),
            'category_id'     => null,
            'seo_title'       => $titleZh.' - 晴空妙享智能家居',
            'seo_description' => $this->faker->sentence(),
            'seo_keywords'    => implode(',', $this->faker->words(5)),
            'is_featured'     => false,
            'sort'            => $this->faker->numberBetween(0, 100),
            'is_published'    => true,
        ];
    }

    /**
     * 未发布状态
     */
    public function unpublished(): static
    {
        return $this->state([
            'is_published' => false,
        ]);
    }

    /**
     * 置顶/精选状态
     */
    public function featured(): static
    {
        return $this->state([
            'is_featured' => true,
        ]);
    }
}
