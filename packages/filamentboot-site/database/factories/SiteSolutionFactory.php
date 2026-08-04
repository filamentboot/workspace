<?php

namespace Filamentboot\FilamentbootSite\Database\Factories;

use Filamentboot\FilamentbootSite\Modules\Corporate\Solutions\Models\SiteSolution;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * 智能方案工厂
 *
 * @extends Factory<SiteSolution>
 */
class SiteSolutionFactory extends Factory
{
    /** @var class-string<SiteSolution> */
    protected $model = SiteSolution::class;

    /**
     * 定义模型默认状态（默认为已发布）
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $titleZh = $this->faker->words(4, true);

        return [
            'title_zh'        => $titleZh,
            'title_en'        => $this->faker->words(4, true),
            'slug'            => Str::slug($titleZh).'-'.$this->faker->unique()->numberBetween(1, 99999),
            'description_zh'  => $this->faker->paragraph(),
            'description_en'  => $this->faker->paragraph(),
            'content_zh'      => $this->faker->paragraphs(3, true),
            'content_en'      => $this->faker->paragraphs(3, true),
            'price_range'     => $this->faker->numberBetween(5, 50).'-'.$this->faker->numberBetween(50, 200).'万',
            'seo_title'       => $titleZh.' - 晴空妙享智能家居',
            'seo_description' => $this->faker->sentence(),
            'seo_keywords'    => implode(',', $this->faker->words(5)),
            'is_featured'     => false,
            'sort'            => $this->faker->numberBetween(0, 100),
            'published_at'    => now(),
        ];
    }

    /**
     * 草稿状态
     */
    public function draft(): static
    {
        return $this->state([
            'published_at' => null,
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
