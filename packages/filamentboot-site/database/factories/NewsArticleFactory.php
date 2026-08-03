<?php

namespace Filamentboot\FilamentbootSite\Database\Factories;

use Filamentboot\FilamentbootSite\Modules\News\Models\NewsArticle;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * 资讯文章工厂
 *
 * @extends Factory<NewsArticle>
 */
class NewsArticleFactory extends Factory
{
    /** @var class-string<NewsArticle> */
    protected $model = NewsArticle::class;

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
            'excerpt_zh'      => $this->faker->sentence(),
            'excerpt_en'      => $this->faker->sentence(),
            'content_zh'      => '<p>'.$this->faker->paragraph().'</p>',
            'content_en'      => '<p>'.$this->faker->paragraph().'</p>',
            'category_id'     => null,
            'seo_title'       => $titleZh.' - 晴空妙享智能家居',
            'seo_description' => $this->faker->sentence(),
            'seo_keywords'    => implode(',', $this->faker->words(5)),
            'is_featured'     => false,
            'sort'            => $this->faker->numberBetween(0, 100),
            'published_at'    => now(),
        ];
    }

    /**
     * 草稿状态（published_at = null）
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
