<?php

namespace Database\Factories;

use App\Enums\DataScope;
use App\Models\RoleDataScope;
use Illuminate\Database\Eloquent\Factories\Factory;
use Spatie\Permission\Models\Role;

/**
 * @extends Factory<RoleDataScope>
 */
class RoleDataScopeFactory extends Factory
{
    protected $model = RoleDataScope::class;

    /**
     * 定义模型的默认状态
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'role_id' => fn () => Role::create([
                'name'       => fake()->unique()->slug(2),
                'guard_name' => 'admin',
            ])->id,
            'scope'          => DataScope::Self,
            'department_ids' => null,
        ];
    }
}
