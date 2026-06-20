<?php

namespace Database\Factories\FilamentAdmin\Models;

use FilamentAdmin\Models\Plugin;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * 插件模型工厂（自动发现命名空间：Database\Factories\{Model full path}）
 *
 * @extends Factory<Plugin>
 */
class PluginFactory extends Factory
{
    /** @var class-string<Plugin> */
    protected $model = Plugin::class;

    /**
     * 定义模型默认状态
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $vendor  = $this->faker->slug(2);
        $package = $this->faker->slug(2);

        return [
            'package_name'      => $vendor.'/'.$package,
            'slug'              => $vendor.'-'.$package,
            'name'              => $this->faker->words(3, true),
            'kind'              => 'package',
            'source'            => 'community',
            'plugin_class'      => null,
            'installed_version' => null,
            'description'       => $this->faker->sentence(),
            'post_install_data' => null,
            'is_enabled'        => false,
            'init_status'       => 'pending',
            'init_log'          => null,
            'installed_at'      => null,
        ];
    }

    /**
     * 已启用状态
     */
    public function enabled(): static
    {
        return $this->state([
            'is_enabled'  => true,
            'init_status' => 'done',
        ]);
    }

    /**
     * 方案型插件（solution_plugin）
     */
    public function solution(): static
    {
        return $this->state([
            'kind'         => 'solution_plugin',
            'plugin_class' => 'App\\Plugins\\'.$this->faker->word().'Plugin',
        ]);
    }
}
