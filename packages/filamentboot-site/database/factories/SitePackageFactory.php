<?php

namespace Filamentboot\FilamentbootSite\Database\Factories;

use Filamentboot\FilamentbootSite\Cms\Enums\PageStatus;
use Filamentboot\FilamentbootSite\Modules\Corporate\Packages\Enums\HouseLayout;
use Filamentboot\FilamentbootSite\Modules\Corporate\Packages\Enums\PackageTier;
use Filamentboot\FilamentbootSite\Modules\Corporate\Packages\Models\SitePackage;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * 全屋智能套餐工厂
 *
 * @extends Factory<SitePackage>
 */
class SitePackageFactory extends Factory
{
    /** @var class-string<SitePackage> */
    protected $model = SitePackage::class;

    /**
     * 定义模型默认状态（默认为已发布）
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $titleZh = $this->faker->words(4, true);

        return [
            'title_zh'       => $titleZh,
            'slug'           => Str::slug($titleZh).'-'.$this->faker->unique()->numberBetween(1, 99999),
            'description_zh' => $this->faker->paragraph(),
            'content_zh'     => $this->faker->paragraphs(3, true),
            'house_layout'   => $this->faker->randomElement(HouseLayout::cases()),
            'tier'           => $this->faker->randomElement(PackageTier::cases()),
            'area_range'     => $this->faker->numberBetween(50, 100).'-'.$this->faker->numberBetween(101, 200).'㎡',
            'price'          => $this->faker->numberBetween(6000, 60000),
            'price_note'     => '参考价，最终以实际量房与选型为准',
            'items'          => [
                ['name' => '智能网关', 'quantity' => '1', 'purpose' => '智能家居核心中枢', 'location' => '客厅'],
                ['name' => '智能开关', 'quantity' => '6', 'purpose' => '控制灯光、电器', 'location' => '各房间'],
            ],
            'excludes'        => '不含灯具本体与强弱电改造',
            'duration'        => '3-5 天',
            'warranty'        => '整机 1 年，施工 2 年',
            'seo_title'       => $titleZh,
            'seo_description' => $this->faker->sentence(),
            'seo_keywords'    => implode(',', $this->faker->words(5)),
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

    /**
     * 不显示价格（前台走「咨询价格」分支）
     */
    public function withoutPrice(): static
    {
        return $this->state([
            'price' => null,
        ]);
    }
}
