<?php

namespace Filamentboot\Tests\Feature\Plugins;

use Filamentboot\FilamentbootServiceProvider;
use Filamentboot\Models\Plugin;
use Filamentboot\Services\PluginManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Mockery;
use Orchestra\Testbench\TestCase;
use RuntimeException;
use Spatie\Activitylog\ActivitylogServiceProvider;
use Spatie\LaravelSettings\LaravelSettingsServiceProvider;
use Spatie\Permission\PermissionServiceProvider;

/**
 * 插件管理器初始化测试（PLUGIN-02 / PLUGIN-03）
 *
 * 覆盖：成功初始化置 init_status=done、失败时保留 init_log、重试整体幂等
 * 注意：失败路径使用 partialMock 拦截内部步骤抛异常，不使用 Artisan::fake，
 * 确保失败路径的 init_log 非空断言真正覆盖日志写入逻辑（Plan 02 WARNING）
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
class PluginManagerInitializeTest extends TestCase
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

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * 创建允许 mock 受保护方法的 PluginManager partialMock
     */
    private function makePluginManagerMock(): PluginManager
    {
        /** @var PluginManager */
        return Mockery::mock(PluginManager::class)
            ->makePartial()
            ->shouldAllowMockingProtectedMethods();
    }

    /**
     * PluginManager initialize 同步执行后 init_status 变为 done
     */
    public function test_initialize_marks_status_done_on_success(): void
    {
        $plugin = Plugin::factory()->create([
            'slug'         => 'test-plugin-success',
            'init_status'  => 'pending',
            'plugin_class' => null,
            'init_log'     => null,
        ]);

        // 用 partialMock 绕过真实 Artisan::call（避免测试环境数据库冲突）
        // runMigrate/runPublish/runSeeder 改为 no-op，但 appendInitLog / updateCacheStatus 等私有方法仍真实执行
        $manager = $this->makePluginManagerMock();
        $manager->shouldReceive('runMigrate')->once()->andReturnUsing(function () {});
        $manager->shouldReceive('runPublish')->once()->andReturnUsing(function () {});
        $manager->shouldReceive('runSeeder')->once()->andReturnUsing(function () {});

        $manager->initialize($plugin);

        $plugin->refresh();

        $this->assertSame('done', $plugin->init_status);

        // Cache 应写入 status=done
        $cacheData = Cache::get('plugin.init.test-plugin-success');
        $this->assertNotNull($cacheData);
        $this->assertSame('done', $cacheData['status']);
    }

    /**
     * 初始化失败时 init_log 保留错误信息且 init_status 为 failed
     */
    public function test_initialize_keeps_init_log_and_marks_status_failed_on_exception(): void
    {
        $plugin = Plugin::factory()->create([
            'slug'         => 'test-plugin-fail',
            'init_status'  => 'pending',
            'plugin_class' => null,
            'init_log'     => null,
        ]);

        // 策略 (a)：partialMock 拦截 runSeeder 抛异常（非 Artisan::fake）
        // 确保失败路径能执行真实的 appendInitLog 日志写入逻辑
        $manager = $this->makePluginManagerMock();
        $manager->shouldReceive('runMigrate')->once()->andReturnUsing(function () {});
        $manager->shouldReceive('runPublish')->once()->andReturnUsing(function () {});
        $manager->shouldReceive('runSeeder')
            ->once()
            ->andThrow(new RuntimeException('Seeder 执行失败：App\\Seeders\\NonExistentSeeder 类不存在'));

        $manager->initialize($plugin);

        $plugin->refresh();

        // 失败路径断言
        $this->assertSame('failed', $plugin->init_status);
        $this->assertNotNull($plugin->init_log);
        $this->assertNotEmpty($plugin->init_log);
        $this->assertStringContainsString('Seeder 执行失败', $plugin->init_log);

        // Cache 应写入 status=failed
        $cacheData = Cache::get('plugin.init.test-plugin-fail');
        $this->assertNotNull($cacheData);
        $this->assertSame('failed', $cacheData['status']);
    }

    /**
     * 重复调用 initialize 整体幂等不抛异常
     */
    public function test_initialize_is_idempotent_when_called_repeatedly(): void
    {
        $plugin = Plugin::factory()->create([
            'slug'         => 'test-plugin-idempotent',
            'init_status'  => 'pending',
            'plugin_class' => null,
            'init_log'     => null,
        ]);

        // partialMock：两次调用均成功（D-06-12 整体幂等）
        $manager = $this->makePluginManagerMock();
        $manager->shouldReceive('runMigrate')->twice()->andReturnUsing(function () {});
        $manager->shouldReceive('runPublish')->twice()->andReturnUsing(function () {});
        $manager->shouldReceive('runSeeder')->twice()->andReturnUsing(function () {});

        // 连续两次调用不抛异常
        $manager->initialize($plugin);
        $manager->initialize($plugin);

        $plugin->refresh();

        // 最终状态仍为 done
        $this->assertSame('done', $plugin->init_status);
    }
}
