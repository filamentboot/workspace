<?php

namespace Filamentboot\Tests\Feature\Plugins;

use Filamentboot\FilamentbootServiceProvider;
use Filamentboot\Jobs\ComposerInstallJob;
use Filamentboot\Models\Plugin;
use Filamentboot\Services\EnvironmentChecker;
use Filamentboot\Services\PluginManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Orchestra\Testbench\TestCase;
use Spatie\Activitylog\ActivitylogServiceProvider;
use Spatie\LaravelSettings\LaravelSettingsServiceProvider;
use Spatie\Permission\PermissionServiceProvider;

/**
 * ComposerInstallJob 分发与状态变更测试（MKTPLACE-02）
 *
 * 覆盖场景：
 * 1. PluginManager::install() 分发 ComposerInstallJob（Queue::fake，不触发真实 composer）
 * 2. 分发时 init_status 立即变为 running
 * 3. 模拟成功完成后 init_status='done' 且 init_log 非空
 *
 * 威胁缓解：T-12-00-01 — 所有断言均使用 Queue::fake()；
 * CI 环境绝不执行真实 composer 子进程。
 *
 * 数据库连接直接沿用根 phpunit.xml 注入的 MySQL 测试库环境变量
 * （本机无 pdo_sqlite 扩展），迁移由 FilamentbootServiceProvider::boot()
 * 的 loadMigrationsFrom 自动注册，无需在测试里重复声明。
 *
 * Testbench 默认 skeleton 没有真实 vendor 目录（未走 workbench 符号链接），
 * Laravel 包自动发现在此环境下失效，因此必须显式注册 Permission /
 * Activitylog / LaravelSettings 三个 ServiceProvider（本包迁移依赖它们的配置）。
 */
class ComposerInstallJobTest extends TestCase
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
     * PluginManager::install 分发 ComposerInstallJob（MKTPLACE-02）
     */
    public function test_install_dispatches_composer_install_job(): void
    {
        Queue::fake();

        $plugin = Plugin::factory()->create([
            'slug'         => 'test-install-plugin',
            'package_name' => 'test/install-plugin',
            'init_status'  => 'pending',
        ]);

        // 环境自检通过：注入 EnvironmentChecker mock
        $checkerMock = Mockery::mock(EnvironmentChecker::class);
        $checkerMock->shouldReceive('check')->once()->andReturn([
            'ok'            => true,
            'composer_path' => '/usr/local/bin/composer',
            'issues'        => [],
        ]);
        $this->app->instance(EnvironmentChecker::class, $checkerMock);

        $manager = $this->app->make(PluginManager::class);
        $manager->install($plugin);

        // 断言：Job 已被推入队列（无真实 composer 执行）
        Queue::assertPushed(ComposerInstallJob::class, function ($job) use ($plugin) {
            return $job->pluginId === $plugin->id
                && $job->packageName === 'test/install-plugin';
        });
    }

    /**
     * install 后 init_status 立即变为 running（MKTPLACE-02）
     */
    public function test_install_immediately_marks_status_running(): void
    {
        Queue::fake();

        $plugin = Plugin::factory()->create([
            'slug'         => 'test-install-running',
            'package_name' => 'test/install-running',
            'init_status'  => 'pending',
        ]);

        $checkerMock = Mockery::mock(EnvironmentChecker::class);
        $checkerMock->shouldReceive('check')->once()->andReturn([
            'ok'            => true,
            'composer_path' => '/usr/local/bin/composer',
            'issues'        => [],
        ]);
        $this->app->instance(EnvironmentChecker::class, $checkerMock);

        $manager = $this->app->make(PluginManager::class);
        $manager->install($plugin);

        // install() 调用后立即将状态置为 running，Job 异步完成余下步骤
        $plugin->refresh();
        $this->assertSame('running', $plugin->init_status);
    }

    /**
     * ComposerInstallJob 成功执行后 init_status=done 且 init_log 非空（MKTPLACE-02）
     */
    public function test_composer_install_job_marks_status_done_with_init_log(): void
    {
        $plugin = Plugin::factory()->create([
            'slug'         => 'test-install-done',
            'package_name' => 'test/install-done',
            'init_status'  => 'running',
            'init_log'     => null,
        ]);

        // 模拟 runComposerInstall 成功（不触发真实 composer 子进程）
        $managerMock = Mockery::mock(PluginManager::class)->makePartial();
        $managerMock->shouldReceive('runComposerInstall')
            ->once()
            ->andReturnUsing(function (Plugin $p, string $pkg) {
                $p->update(['init_status' => 'done', 'init_log' => 'composer require output OK']);
            });
        $this->app->instance(PluginManager::class, $managerMock);

        $job = new ComposerInstallJob($plugin->id, 'test/install-done');
        $job->handle($managerMock);

        $plugin->refresh();
        $this->assertSame('done', $plugin->init_status);
        $this->assertNotNull($plugin->init_log);
        $this->assertNotEmpty($plugin->init_log);
    }
}
