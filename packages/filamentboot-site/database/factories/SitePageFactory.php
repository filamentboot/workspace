<?php

namespace Filamentboot\FilamentbootSite\Database\Factories;

use Filamentboot\FilamentbootSite\Cms\Enums\PageStatus;
use Filamentboot\FilamentbootSite\Cms\Models\SitePage;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * CMS 页面工厂
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
            'slug'            => Str::slug($titleZh).'-'.$this->faker->unique()->numberBetween(1, 99999),
            'template'        => 'default',
            'content_zh'      => $this->faker->paragraphs(3, true),
            'seo_title'       => $titleZh,
            'seo_description' => $this->faker->sentence(),
            'seo_keywords'    => implode(',', $this->faker->words(5)),
            'sort'            => $this->faker->numberBetween(0, 100),
            'status'          => PageStatus::PUBLISHED,
            'published_at'    => now()->subDay(),
        ];
    }

    /**
     * 未发布状态（草稿）
     *
     * 保留 unpublished() 名字供既有测试调用，语义等同 draft()。
     */
    public function unpublished(): static
    {
        return $this->draft();
    }

    /**
     * 草稿
     */
    public function draft(): static
    {
        return $this->state([
            'status'       => PageStatus::DRAFT,
            'published_at' => null,
        ]);
    }

    /**
     * 待审核
     */
    public function review(): static
    {
        return $this->state([
            'status'       => PageStatus::REVIEW,
            'published_at' => null,
        ]);
    }

    /**
     * 定时发布（默认发布时间在未来，前台不可见）
     */
    public function scheduled(?\DateTimeInterface $at = null): static
    {
        return $this->state([
            'status'       => PageStatus::SCHEDULED,
            'published_at' => $at ?? now()->addDay(),
        ]);
    }

    /**
     * 已归档
     */
    public function archived(): static
    {
        return $this->state([
            'status' => PageStatus::ARCHIVED,
        ]);
    }
}
