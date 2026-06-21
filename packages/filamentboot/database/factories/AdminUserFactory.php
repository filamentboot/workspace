<?php

namespace FilamentAdmin\Database\Factories;

use FilamentAdmin\Enums\AdminUserStatus;
use FilamentAdmin\Models\AdminUser;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * AdminUser 模型工厂
 *
 * @extends Factory<AdminUser>
 */
class AdminUserFactory extends Factory
{
    /** @var class-string<AdminUser> */
    protected $model = AdminUser::class;

    /**
     * 定义模型的默认状态
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'account'           => fake()->unique()->userName(),
            'email'             => fake()->unique()->safeEmail(),
            'nickname'          => fake()->name(),
            'status'            => AdminUserStatus::Active,
            'password'          => 'password', // 会被 hashed cast 自动哈希
            'email_verified_at' => now(),
            'remember_token'    => Str::random(10),
        ];
    }

    /**
     * 未验证邮箱的用户状态
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    /**
     * 已启用 2FA 的用户状态
     */
    public function withTwoFactor(): static
    {
        return $this->state(fn (array $attributes) => [
            'two_factor_secret'         => encrypt('test-secret'),
            'two_factor_recovery_codes' => encrypt(json_encode(['code1', 'code2'])),
            'two_factor_confirmed_at'   => now(),
        ]);
    }

    /**
     * 禁用管理员状态
     */
    public function disabled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => AdminUserStatus::Disabled,
        ]);
    }
}
