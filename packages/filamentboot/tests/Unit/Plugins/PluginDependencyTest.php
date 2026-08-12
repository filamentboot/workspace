<?php

namespace Filamentboot\Tests\Unit\Plugins;

use Filamentboot\FilamentbootServiceProvider;
use Filamentboot\Models\Plugin;
use Filamentboot\Services\PluginManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Orchestra\Testbench\TestCase;
use Spatie\Activitylog\ActivitylogServiceProvider;
use Spatie\LaravelSettings\LaravelSettingsServiceProvider;
use Spatie\Permission\PermissionServiceProvider;

/**
 * 插件依赖关系测试（PLUGIN-06）
 *
 * Phase 12（D-12-09）: compatibility 字段已从 plugins 表移除；
 * 兼容性比对迁移至 Packagist p2 端点（MKTPLACE-05，Plan 02）。
 * 本文件测试 checkDependencies 在 Phase 12 语义下的行为：
 * 无本地 compatibility 字段时返回空数组（无阻塞），启用操作不被阻断。
 *
 * 数据库连接直接沿用根 phpunit.xml 注入的 MySQL 测试库环境变量
 * （本机无 pdo_sqlite 扩展），迁移由 FilamentbootServiceProvider::boot()
 * 的 loadMigrationsFrom 自动注册，无需在测试里重复声明。
 *
 * Testbench 默认 skeleton 没有真实 vendor 目录（未走 workbench 符号链接），
 * Laravel 包自动发现在此环境下失效：migrate:fresh 会跑到本包
 * create_permission_tables 迁移（读 config('permission.table_names')），
 * 因此必须显式注册 Permission / Activitylog 两个 ServiceProvider
 * （否则报 "config/permission.php not loaded"）。
 */
class PluginDependencyTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return list<class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [
            FilamentbootServiceProvider::class,
            PermissionServiceProvider::class,
            ActivitylogServiceProvider::class,
            LaravelSettingsServiceProvider::class,
        ];
    }

    /**
     * checkDependencies Phase 12 语义：无本地 compatibility 字段时返回空数组
     */
    public function test_check_dependencies_returns_empty_array_without_local_compatibility(): void
    {
        $plugin = Plugin::factory()->create([
            'is_enabled' => false,
        ]);

        $manager = new PluginManager;
        $issues  = $manager->checkDependencies($plugin);

        // Phase 12: compatibility 已移至 Packagist p2，本地无约束时返回空
        $this->assertEmpty($issues);
    }

    /**
     * checkDependencies 返回空数组时 enable 不抛异常
     */
    public function test_enable_does_not_throw_when_check_dependencies_is_empty(): void
    {
        $plugin = Plugin::factory()->create([
            'is_enabled' => false,
        ]);

        $manager = new PluginManager;

        // Phase 12: 无 compatibility 字段，enable 不被阻断
        $manager->enable($plugin);

        $this->assertTrue($plugin->refresh()->is_enabled);
    }
}
