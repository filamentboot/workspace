<?php

namespace Filamentboot\FilamentbootSite\Database\Factories;

use Filamentboot\FilamentbootSite\Models\SiteTag;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * 内容标签工厂
 *
 * @extends Factory<SiteTag>
 */
class SiteTagFactory extends Factory
{
    /** @var class-string<SiteTag> */
    protected $model = SiteTag::class;

    /**
     * 定义模型默认状态
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $nameZh = $this->faker->words(2, true);

        return [
            'name_zh' => $nameZh,
            'slug'    => Str::slug($nameZh).'-'.$this->faker->unique()->numberBetween(1, 9999),
        ];
    }
}
