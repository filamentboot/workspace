<?php

use Filamentboot\Jobs\ComposerRemoveJob;
use Filamentboot\Models\Plugin;
use Filamentboot\Services\PluginManager;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Queue;

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
 */
it('PluginManager::uninstall 分发 ComposerRemoveJob（MKTPLACE-06）', function () {
    Queue::fake();

    $plugin = Plugin::factory()->create([
        'slug'         => 'test-remove-plugin',
        'package_name' => 'test/remove-plugin',
        'is_enabled'   => true,
        'init_status'  => 'done',
    ]);

    $manager = app(PluginManager::class);
    $manager->uninstall($plugin);

    // 断言：Job 已被推入队列
    Queue::assertPushed(ComposerRemoveJob::class, function ($job) use ($plugin) {
        return $job->pluginId === $plugin->id;
    });
});

it('uninstall 先将插件 disable 再分发移除 Job（MKTPLACE-06 Pitfall 5）', function () {
    Queue::fake();

    $plugin = Plugin::factory()->create([
        'slug'         => 'test-disable-before-remove',
        'package_name' => 'test/disable-before-remove',
        'is_enabled'   => true,
        'init_status'  => 'done',
    ]);

    $manager = app(PluginManager::class);
    $manager->uninstall($plugin);

    // 断言：uninstall() 同步执行 disable（is_enabled=false），然后分发 Job
    // 防止 class-not-found 崩溃（RESEARCH Pitfall 5）
    $plugin->refresh();
    expect($plugin->is_enabled)->toBeFalse();

    Queue::assertPushed(ComposerRemoveJob::class);
});

it('ComposerRemoveJob 执行后 plugins 记录被软删除且 optimize:clear 已运行（MKTPLACE-06）', function () {
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
    expect(Plugin::find($pluginId))->toBeNull();
    expect(Plugin::withTrashed()->find($pluginId))->not->toBeNull();
});
