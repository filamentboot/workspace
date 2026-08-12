<?php

namespace Filamentboot\FilamentbootSite\Database\Factories;

use Filamentboot\FilamentbootSite\Cms\Enums\PageStatus;
use Filamentboot\FilamentbootSite\Modules\Corporate\Cases\Enums\CaseStyle;
use Filamentboot\FilamentbootSite\Modules\Corporate\Cases\Enums\HouseType;
use Filamentboot\FilamentbootSite\Modules\Corporate\Cases\Models\SiteCase;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * 装修案例工厂
 *
 * @extends Factory<SiteCase>
 */
class SiteCaseFactory extends Factory
{
    /** @var class-string<SiteCase> */
    protected $model = SiteCase::class;

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
            'slug'            => Str::slug($titleZh).'-'.$this->faker->unique()->numberBetween(1, 99999),
            'style'           => $this->faker->randomElement(CaseStyle::cases())->value,
            'house_type'      => $this->faker->randomElement(HouseType::cases())->value,
            'area'            => $this->faker->numberBetween(50, 500).'㎡',
            'budget_range'    => $this->faker->numberBetween(10, 50).'-'.$this->faker->numberBetween(50, 200).'万',
            'smart_features'  => $this->faker->sentences(2, true),
            'description_zh'  => $this->faker->paragraph(),
            'content_zh'      => $this->faker->paragraphs(3, true),
            'seo_title'       => $titleZh,
            'seo_description' => $this->faker->sentence(),
            'seo_keywords'    => implode(',', $this->faker->words(5)),
            'category_id'     => null,
            'is_featured'     => false,
            'sort'            => $this->faker->numberBetween(0, 100),
            'status'          => PageStatus::PUBLISHED,
            'published_at'    => now(),
        ];
    }

    /**
     * 草稿状态
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
