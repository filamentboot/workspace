<?php

namespace FilamentAdmin\Database\Factories;

use FilamentAdmin\Models\Menu;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Menu>
 */
class MenuFactory extends Factory
{
    protected $model = Menu::class;

    /**
     * 定义模型的默认状态
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'parent_id'       => null,
            'title'           => fake()->unique()->words(2, true),
            'icon'            => 'heroicon-o-bars-3',
            'route_name'      => null,
            'url'             => fake()->url(),
            'permission_name' => null,
            'sort'            => fake()->numberBetween(0, 100),
            'is_active'       => true,
            'target'          => 'self',
            'source'          => 'core',
        ];
    }
}
