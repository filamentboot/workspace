<?php

namespace FilamentAdmin\Database\Factories;

use FilamentAdmin\Models\Department;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Department>
 */
class DepartmentFactory extends Factory
{
    protected $model = Department::class;

    /**
     * 定义模型的默认状态
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'parent_id'            => null,
            'name'                 => fake()->company(),
            'code'                 => strtoupper(fake()->unique()->bothify('DEPT##??')),
            'leader_admin_user_id' => null,
            'sort'                 => fake()->numberBetween(0, 100),
            'is_active'            => true,
        ];
    }
}
