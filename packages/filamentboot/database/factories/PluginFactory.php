<?php

namespace Filamentboot\Database\Factories;

use Filamentboot\Models\Plugin;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Plugin 模型工厂
 *
 * @extends Factory<Plugin>
 */
class PluginFactory extends Factory
{
    /** @var class-string<Plugin> */
    protected $model = Plugin::class;

    /**
     * 定义模型的默认状态
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $vendor = $this->faker->slug(1);
        $name   = $this->faker->slug(1);

        return [
            'package_name'       => $vendor.'/'.$name,
            'slug'               => $vendor.'-'.$name,
            'name'               => $this->faker->words(2, true),
            'kind'               => 'package',
            'source'             => 'community',
            'plugin_class'       => null,
            'settings_page_slug' => null,
            'service_provider'   => null,
            'install_constraint' => null,
            'installed_version'  => '1.0.0',
            'description'        => $this->faker->sentence(),
            'compatibility_status' => 'unknown',
            'is_enabled'         => false,
            'init_status'        => 'pending',
            'init_log'           => null,
            'installed_at'       => null,
        ];
    }
}
