<?php

namespace Filamentboot\Tests\Unit;

use Filamentboot\FilamentbootServiceProvider;
use Illuminate\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use Orchestra\Testbench\TestCase;

/**
 * ServiceProvider publishes 注册测试
 *
 * 验证 FilamentbootServiceProvider 注册了 5 个 vendor:publish 标签（COMPLY-01）：
 * filamentboot-config / filamentboot-migrations / filamentboot-views /
 * filamentboot-lang / filamentboot-stubs
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
        return [FilamentbootServiceProvider::class];
    }

    /**
     * 验证 filamentboot-config 发布标签已注册，且源路径与目标路径映射正确
     */
    public function test_service_provider_registers_filament_admin_config_publish_tag(): void
    {
        $paths = ServiceProvider::pathsToPublish(
            FilamentbootServiceProvider::class,
            'filamentboot-config'
        );

        self::assertNotEmpty($paths, '配置 publish tag 未在 ServiceProvider 中注册');

        $found = false;

        foreach ($paths as $source => $target) {
            if (str_ends_with($source, 'config/filamentboot.php')
                && $target === config_path('filamentboot.php')) {
                $found = true;

                break;
            }
        }

        self::assertTrue($found, '未找到 config/filamentboot.php → config_path 的映射');
    }

    /**
     * 验证 filamentboot-migrations 发布标签已注册，且源目录与目标目录映射正确
     */
    public function test_service_provider_registers_filament_admin_migrations_publish_tag(): void
    {
        $paths = ServiceProvider::pathsToPublish(
            FilamentbootServiceProvider::class,
            'filamentboot-migrations'
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
     * 验证 filamentboot-views 发布标签已注册，且映射到 resource_path('views/vendor/filamentboot')
     */
    public function test_service_provider_registers_filament_admin_views_publish_tag(): void
    {
        $paths = ServiceProvider::pathsToPublish(
            FilamentbootServiceProvider::class,
            'filamentboot-views'
        );

        self::assertNotEmpty($paths, '视图 publish tag 未在 ServiceProvider 中注册');

        $found = false;

        foreach ($paths as $source => $target) {
            if (str_ends_with($source, 'resources/views')
                && $target === resource_path('views/vendor/filamentboot')) {
                $found = true;

                break;
            }
        }

        self::assertTrue($found, '未找到 resources/views → resource_path(views/vendor/filamentboot) 的映射');
    }

    /**
     * 验证 filamentboot-lang 发布标签已注册，且映射到 langPath('vendor/filamentboot')
     */
    public function test_service_provider_registers_filament_admin_lang_publish_tag(): void
    {
        $paths = ServiceProvider::pathsToPublish(
            FilamentbootServiceProvider::class,
            'filamentboot-lang'
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
     * 验证 filamentboot-stubs 发布标签已注册，且映射到 base_path('stubs/vendor/filamentboot')
     */
    public function test_service_provider_registers_filament_admin_stubs_publish_tag(): void
    {
        $paths = ServiceProvider::pathsToPublish(
            FilamentbootServiceProvider::class,
            'filamentboot-stubs'
        );

        self::assertNotEmpty($paths, 'Stubs publish tag 未在 ServiceProvider 中注册');

        $found = false;

        foreach ($paths as $source => $target) {
            if (str_ends_with($source, 'stubs')
                && $target === base_path('stubs/vendor/filamentboot')) {
                $found = true;

                break;
            }
        }

        self::assertTrue($found, '未找到 stubs → base_path(stubs/vendor/filamentboot) 的映射');
    }
}
