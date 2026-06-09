<?php

namespace FilamentAdmin\Tests\Unit;

use FilamentAdmin\FilamentAdminServiceProvider;
use Illuminate\Support\ServiceProvider;
use Orchestra\Testbench\TestCase;

/**
 * ServiceProvider publishes 注册测试
 *
 * 验证 FilamentAdminServiceProvider 注册了 5 个 vendor:publish 标签（COMPLY-01）：
 * filament-admin-config / filament-admin-migrations / filament-admin-views /
 * filament-admin-lang / filament-admin-stubs
 *
 * 本测试骨架在 Plan 03（ServiceProvider 注册 5 个 publishes）完成后由红转绿。
 */
class ServiceProviderPublishesTest extends TestCase
{
    /**
     * 返回需要注册的包服务提供者
     *
     * @param  \Illuminate\Foundation\Application  $app
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
        $this->markTestIncomplete('待 Plan 03 ServiceProvider 注册 5 个 publishes 后启用');
    }

    /**
     * 验证 filament-admin-migrations 发布标签已注册，且源目录与目标目录映射正确
     */
    public function test_service_provider_registers_filament_admin_migrations_publish_tag(): void
    {
        $this->markTestIncomplete('待 Plan 03 ServiceProvider 注册 5 个 publishes 后启用');
    }

    /**
     * 验证 filament-admin-views 发布标签已注册，且映射到 resource_path('views/vendor/filament-admin')
     */
    public function test_service_provider_registers_filament_admin_views_publish_tag(): void
    {
        $this->markTestIncomplete('待 Plan 03 ServiceProvider 注册 5 个 publishes 后启用');
    }

    /**
     * 验证 filament-admin-lang 发布标签已注册，且映射到 langPath('vendor/filament-admin')
     */
    public function test_service_provider_registers_filament_admin_lang_publish_tag(): void
    {
        $this->markTestIncomplete('待 Plan 03 ServiceProvider 注册 5 个 publishes 后启用');
    }

    /**
     * 验证 filament-admin-stubs 发布标签已注册，且映射到 base_path('stubs/vendor/filament-admin')
     */
    public function test_service_provider_registers_filament_admin_stubs_publish_tag(): void
    {
        $this->markTestIncomplete('待 Plan 03 ServiceProvider 注册 5 个 publishes 后启用');
    }
}
