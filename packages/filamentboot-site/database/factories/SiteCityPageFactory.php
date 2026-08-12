<?php

namespace Filamentboot\FilamentbootSite\Database\Factories;

use Filamentboot\FilamentbootSite\Cms\Enums\PageStatus;
use Filamentboot\FilamentbootSite\Modules\Corporate\Cities\Models\SiteCityPage;
use Filamentboot\FilamentbootSite\Modules\Corporate\Cities\Models\SiteRegion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * 城市页工厂
 *
 * 默认已发布，并**自动造一个地级区划挂上去**——城市页离了区划连 URL 都拼不出来，
 * 让每个用例自己先造区划只会让每个用例都写同样三行。
 * 要指定区划就传 `['region_code' => $region->code]`。
 *
 * `content_zh` 默认为 null，与线上一致：正文是可选覆写，正常不填。
 *
 * @extends Factory<SiteCityPage>
 */
class SiteCityPageFactory extends Factory
{
    /** @var class-string<SiteCityPage> */
    protected $model = SiteCityPage::class;

    /**
     * 定义模型默认状态（已发布）
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'region_code'    => fn (): string => SiteRegion::factory()->create()->code,
            'title_zh'       => $this->faker->word().'全屋智能装修',
            'description_zh' => $this->faker->sentence(),
            'content_zh'     => null,
            'profile'        => [],
            'sort'           => 0,
            'status'         => PageStatus::PUBLISHED,
            'published_at'   => now(),
        ];
    }

    /**
     * 草稿状态
     */
    public function draft(): static
    {
        return $this->state(['status' => PageStatus::DRAFT, 'published_at' => null]);
    }

    /**
     * 待审核
     */
    public function review(): static
    {
        return $this->state(['status' => PageStatus::REVIEW, 'published_at' => null]);
    }

    /**
     * 定时发布（默认发布时间在未来，前台不可见）
     */
    public function scheduled(?\DateTimeInterface $at = null): static
    {
        return $this->state(['status' => PageStatus::SCHEDULED, 'published_at' => $at ?? now()->addDay()]);
    }

    /**
     * 已归档
     */
    public function archived(): static
    {
        return $this->state(['status' => PageStatus::ARCHIVED]);
    }

    /**
     * 挂到指定区划上
     */
    public function forRegion(SiteRegion $region): static
    {
        return $this->state(['region_code' => $region->code]);
    }
}
