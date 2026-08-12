<?php

namespace Filamentboot\Tests\Feature\Plugins;

use Filamentboot\FilamentbootServiceProvider;
use Filamentboot\Jobs\ComposerRemoveJob;
use Filamentboot\Models\Plugin;
use Filamentboot\Services\PluginManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Orchestra\Testbench\TestCase;
use Spatie\Activitylog\ActivitylogServiceProvider;
use Spatie\LaravelSettings\LaravelSettingsServiceProvider;
use Spatie\Permission\PermissionServiceProvider;

/**
 * ComposerRemoveJob 分发与卸载流程顺序测试（MKTPLACE-06）
 *
 * 覆盖场景：
 * 1. PluginManager::uninstall() 先 disable 再分发 ComposerRemoveJob（Queue::fake）
 * 2. 卸载前先调用 disable()（is_enabled=false），再移除，再删记录，最后 optimize:clear
 *    （RESEARCH Pitfall 5：卸载顺序必须为 disable→remove→delete record→optimize:clear）
 * 3. ComposerRemoveJob::handle() 执行后 plugins 记录被软删除
 *
 * 威胁缓解：T-12-00-01 — Queue::fake() 确保 CI 不执行真实 composer 子进程。
 *
 * 数据库连接直接沿用根 phpunit.xml 注入的 MySQL 测试库环境变量
 * （本机无 pdo_sqlite 扩展），迁移由 FilamentbootServiceProvider::boot()
 * 的 loadMigrationsFrom 自动注册，无需在测试里重复声明。
 *
 * Testbench 默认 skeleton 没有真实 vendor 目录（未走 workbench 符号链接），
 * Laravel 包自动发现在此环境下失效，因此必须显式注册 Permission /
 * Activitylog / LaravelSettings 三个 ServiceProvider（本包迁移依赖它们的配置）。
 */
class ComposerRemoveJobTest extends TestCase
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
     * PluginManager::uninstall 分发 ComposerRemoveJob（MKTPLACE-06）
     */
    public function test_uninstall_dispatches_composer_remove_job(): void
    {
        Queue::fake();

        $plugin = Plugin::factory()->create([
            'slug'         => 'test-remove-plugin',
            'package_name' => 'test/remove-plugin',
            'is_enabled'   => true,
            'init_status'  => 'done',
        ]);

        $manager = $this->app->make(PluginManager::class);
        $manager->uninstall($plugin);

        // 断言：Job 已被推入队列
        Queue::assertPushed(ComposerRemoveJob::class, function ($job) use ($plugin) {
            return $job->pluginId === $plugin->id;
        });
    }

    /**
     * uninstall 先将插件 disable 再分发移除 Job（MKTPLACE-06 Pitfall 5）
     */
    public function test_uninstall_disables_plugin_before_dispatching_remove_job(): void
    {
        Queue::fake();

        $plugin = Plugin::factory()->create([
            'slug'         => 'test-disable-before-remove',
            'package_name' => 'test/disable-before-remove',
            'is_enabled'   => true,
            'init_status'  => 'done',
        ]);

        $manager = $this->app->make(PluginManager::class);
        $manager->uninstall($plugin);

        // 断言：uninstall() 同步执行 disable（is_enabled=false），然后分发 Job
        // 防止 class-not-found 崩溃（RESEARCH Pitfall 5）
        $plugin->refresh();
        $this->assertFalse($plugin->is_enabled);

        Queue::assertPushed(ComposerRemoveJob::class);
    }

    /**
     * ComposerRemoveJob 执行后 plugins 记录被软删除且 optimize:clear 已运行（MKTPLACE-06）
     */
    public function test_composer_remove_job_soft_deletes_plugin_and_runs_optimize_clear(): void
    {
        $plugin = Plugin::factory()->create([
            'slug'         => 'test-remove-done',
            'package_name' => 'test/remove-done',
            'is_enabled'   => false,
            'init_status'  => 'done',
        ]);

        $pluginId = $plugin->id;

        // 模拟 runComposerRemove（不触发真实 composer 子进程）
        $managerMock = Mockery::mock(PluginManager::class)->makePartial();
        $managerMock->shouldReceive('runComposerRemove')
            ->once()
            ->andReturnUsing(function (Plugin $p, string $pkg, bool $drop) {
                $p->delete();
                Artisan::call('optimize:clear');
            });

        $job = new ComposerRemoveJob($pluginId, 'test/remove-done');
        $job->handle($managerMock);

        // 断言：记录已被软删除
        $this->assertNull(Plugin::find($pluginId));
        $this->assertNotNull(Plugin::withTrashed()->find($pluginId));
    }
}
