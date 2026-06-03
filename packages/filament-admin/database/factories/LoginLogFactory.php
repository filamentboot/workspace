<?php

namespace Database\Factories;

use FilamentAdmin\Models\AdminUser;
use FilamentAdmin\Models\LoginLog;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LoginLog>
 */
class LoginLogFactory extends Factory
{
    protected $model = LoginLog::class;

    /**
     * 定义模型的默认状态
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $status = fake()->randomElement(['success', 'failed']);

        return [
            'admin_user_id'  => $status === 'success' ? AdminUser::factory() : null,
            'username'       => fake()->userName(),
            'status'         => $status,
            'ip_address'     => fake()->ipv4(),
            'user_agent'     => fake()->userAgent(),
            'failure_reason' => $status === 'failed' ? 'invalid_credentials' : null,
            'created_at'     => now(),
        ];
    }

    /**
     * 成功登录状态
     */
    public function success(): static
    {
        return $this->state(fn (array $attributes) => [
            'status'         => 'success',
            'failure_reason' => null,
            'admin_user_id'  => AdminUser::factory(),
        ]);
    }

    /**
     * 失败登录状态
     */
    public function failed(?string $reason = 'invalid_credentials'): static
    {
        return $this->state(fn (array $attributes) => [
            'status'         => 'failed',
            'failure_reason' => $reason,
            'admin_user_id'  => null,
        ]);
    }
}
