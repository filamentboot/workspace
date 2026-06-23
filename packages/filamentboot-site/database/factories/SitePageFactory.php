<?php

namespace Filamentboot\FilamentbootSite\Database\Factories;

use Filamentboot\FilamentbootSite\Models\SitePage;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * 静态页面工厂
 *
 * @extends Factory<SitePage>
 */
class SitePageFactory extends Factory
{
    /** @var class-string<SitePage> */
    protected $model = SitePage::class;

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
            'content_zh'      => $this->faker->paragraphs(3, true),
            'content_en'      => $this->faker->paragraphs(3, true),
            'seo_title'       => $titleZh.' - 晴空妙享智能家居',
            'seo_description' => $this->faker->sentence(),
            'seo_keywords'    => implode(',', $this->faker->words(5)),
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
}
