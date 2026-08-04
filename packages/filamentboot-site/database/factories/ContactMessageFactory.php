<?php

namespace Filamentboot\FilamentbootSite\Database\Factories;

use Filamentboot\FilamentbootSite\Enums\ContactMessageStatus;
use Filamentboot\FilamentbootSite\Models\ContactMessage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * 询盘工厂
 *
 * @extends Factory<ContactMessage>
 */
class ContactMessageFactory extends Factory
{
    /** @var class-string<ContactMessage> */
    protected $model = ContactMessage::class;

    /**
     * 定义模型默认状态
     *
     * 默认不带首触归因字段（landing_url / referer / utm_*）：绝大多数直接访问的
     * 访客本来就没有这些值，默认造出来反而让「归因缺失」这条路径测不到。
     * 需要时用 withAttribution() 状态。
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name'    => $this->faker->name(),
            'phone'   => (string) $this->faker->numberBetween(13000000000, 18999999999),
            'message' => $this->faker->sentence(),
            'status'  => ContactMessageStatus::UNREAD,
            'ip'      => $this->faker->ipv4(),
            'source'  => $this->faker->randomElement(['floating', 'hero', 'nav-desktop', 'page-cta']),
        ];
    }

    /**
     * 已读
     */
    public function read(): static
    {
        return $this->state(fn (): array => ['status' => ContactMessageStatus::READ]);
    }

    /**
     * 带首触渠道归因（A1）
     */
    public function withAttribution(): static
    {
        return $this->state(fn (): array => [
            'landing_url'  => $this->faker->url(),
            'referer'      => 'https://www.baidu.com/',
            'utm_source'   => 'baidu',
            'utm_medium'   => 'cpc',
            'utm_campaign' => $this->faker->slug(2),
        ]);
    }
}
