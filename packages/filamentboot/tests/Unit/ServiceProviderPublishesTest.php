<?php

namespace FilamentAdmin\Tests\Unit;

use FilamentAdmin\FilamentAdminServiceProvider;
use Illuminate\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use Orchestra\Testbench\TestCase;

/**
 * ServiceProvider publishes 注册测试
 *
 * 验证 FilamentAdminServiceProvider 注册了 5 个 vendor:publish 标签（COMPLY-01）：
 * filament-admin-config / filament-admin-migrations / filament-admin-views /
 * filament-admin-lang / filament-admin-stubs
 */
class ServiceProviderPublishesTest extends TestCase
{
    /**
     * 返回需要注册的包服务提供者
     *
     * @param  Application  $app
     * @return list<class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [FilamentAdminServiceProvider::class];
    }

    /**
     * 验证 filament-admin-config 发布标签已注册，且源路径与目标路径映射正确
     */
    public function test_service_provider_registers_filament_admin_config_publish_tag(): void
    {
        $paths = ServiceProvider::pathsToPublish(
            FilamentAdminServiceProvider::class,
            'filament-admin-config'
        );

        self::assertNotEmpty($paths, '配置 publish tag 未在 ServiceProvider 中注册');

        $found = false;

        foreach ($paths as $source => $target) {
            if (str_ends_with($source, 'config/filament-admin.php')
                && $target === config_path('filament-admin.php')) {
                $found = true;

                break;
            }
        }

        self::assertTrue($found, '未找到 config/filament-admin.php → config_path 的映射');
    }

    /**
     * 验证 filament-admin-migrations 发布标签已注册，且源目录与目标目录映射正确
     */
    public function test_service_provider_registers_filament_admin_migrations_publish_tag(): void
    {
        $paths = ServiceProvider::pathsToPublish(
            FilamentAdminServiceProvider::class,
            'filament-admin-migrations'
        );

        self::assertNotEmpty($paths, '迁移 publish tag 未在 ServiceProvider 中注册');

        $found = false;

        foreach ($paths as $source => $target) {
            if ((str_ends_with($source, 'database/migrations') || str_ends_with($source, 'database/migrations/'))
                && $target === database_path('migrations')) {
                $found = true;

                break;
            }
        }

        self::assertTrue($found, '未找到 database/migrations → database_path(migrations) 的映射');
    }

    /**
     * 验证 filament-admin-views 发布标签已注册，且映射到 resource_path('views/vendor/filament-admin')
     */
    public function test_service_provider_registers_filament_admin_views_publish_tag(): void
    {
        $paths = ServiceProvider::pathsToPublish(
            FilamentAdminServiceProvider::class,
            'filament-admin-views'
        );

        self::assertNotEmpty($paths, '视图 publish tag 未在 ServiceProvider 中注册');

        $found = false;

        foreach ($paths as $source => $target) {
            if (str_ends_with($source, 'resources/views')
                && $target === resource_path('views/vendor/filament-admin')) {
                $found = true;

                break;
            }
        }

        self::assertTrue($found, '未找到 resources/views → resource_path(views/vendor/filament-admin) 的映射');
    }

    /**
     * 验证 filament-admin-lang 发布标签已注册，且映射到 langPath('vendor/filament-admin')
     */
    public function test_service_provider_registers_filament_admin_lang_publish_tag(): void
    {
        $paths = ServiceProvider::pathsToPublish(
            FilamentAdminServiceProvider::class,
            'filament-admin-lang'
        );

        self::assertNotEmpty($paths, '翻译 publish tag 未在 ServiceProvider 中注册');

        $sources = array_keys($paths);

        self::assertTrue(
            (bool) array_filter($sources, fn ($s) => str_ends_with($s, '/lang/en')),
            '缺 lang/en 映射'
        );
        self::assertTrue(
            (bool) array_filter($sources, fn ($s) => str_ends_with($s, '/lang/zh_CN')),
            '缺 lang/zh_CN 映射'
        );
    }

    /**
     * 验证 filament-admin-stubs 发布标签已注册，且映射到 base_path('stubs/vendor/filament-admin')
     */
    public function test_service_provider_registers_filament_admin_stubs_publish_tag(): void
    {
        $paths = ServiceProvider::pathsToPublish(
            FilamentAdminServiceProvider::class,
            'filament-admin-stubs'
        );

        self::assertNotEmpty($paths, 'Stubs publish tag 未在 ServiceProvider 中注册');

        $found = false;

        foreach ($paths as $source => $target) {
            if (str_ends_with($source, 'stubs')
                && $target === base_path('stubs/vendor/filament-admin')) {
                $found = true;

                break;
            }
        }

        self::assertTrue($found, '未找到 stubs → base_path(stubs/vendor/filament-admin) 的映射');
    }
}
