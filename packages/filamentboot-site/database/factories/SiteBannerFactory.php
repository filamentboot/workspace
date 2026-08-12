<?php

namespace Filamentboot\FilamentbootSite\Database\Factories;

use Filamentboot\FilamentbootSite\Modules\Corporate\Banners\Enums\BannerCtaAction;
use Filamentboot\FilamentbootSite\Modules\Corporate\Banners\Enums\BannerPosition;
use Filamentboot\FilamentbootSite\Modules\Corporate\Banners\Models\SiteBanner;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * 幻灯片工厂
 *
 * @extends Factory<SiteBanner>
 */
class SiteBannerFactory extends Factory
{
    /** @var class-string<SiteBanner> */
    protected $model = SiteBanner::class;

    /**
     * 定义模型默认状态（默认为已启用、无时间窗、投放首页）
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = $this->faker->words(4, true);

        return [
            'slug'       => Str::slug($title).'-'.$this->faker->unique()->numberBetween(1, 99999),
            'title'      => $title,
            'subtitle'   => $this->faker->sentence(),
            'cta_label'  => '了解更多',
            'cta_url'    => '/cases',
            'cta_action' => BannerCtaAction::LINK,
            'position'   => BannerPosition::HOME_TOP,
            'sort'       => $this->faker->numberBetween(0, 100),
            'starts_at'  => null,
            'ends_at'    => null,
            'is_enabled' => true,
        ];
    }

    /**
     * 停用状态
     */
    public function disabled(): static
    {
        return $this->state([
            'is_enabled' => false,
        ]);
    }

    /**
     * 尚未开始投放（starts_at 在未来）
     */
    public function scheduled(): static
    {
        return $this->state([
            'starts_at' => now()->addDay(),
        ]);
    }

    /**
     * 已过期（ends_at 在过去）
     */
    public function expired(): static
    {
        return $this->state([
            'ends_at' => now()->subDay(),
        ]);
    }

    /**
     * 按钮触发询盘面板而非跳转
     */
    public function inquiry(): static
    {
        return $this->state([
            'cta_action' => BannerCtaAction::INQUIRY,
            'cta_url'    => null,
        ]);
    }

    /**
     * 不显示按钮
     */
    public function withoutCta(): static
    {
        return $this->state([
            'cta_action' => BannerCtaAction::NONE,
            'cta_label'  => null,
            'cta_url'    => null,
        ]);
    }
}
