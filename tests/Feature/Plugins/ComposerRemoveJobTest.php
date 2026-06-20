<?php

use FilamentAdmin\Models\Plugin;
use Illuminate\Support\Facades\Queue;

/**
 * ComposerRemoveJob 分发与卸载流程顺序测试（MKTPLACE-06）
 *
 * 覆盖场景：
 * 1. PluginManager::uninstall() 分发 ComposerRemoveJob（Queue::fake，不触发真实 composer）
 * 2. 卸载前先调用 disable()（is_enabled=false），再移除，再删记录，最后 optimize:clear
 *    （RESEARCH Pitfall 5：卸载顺序必须为 disable→remove→delete record→optimize:clear）
 *
 * 威胁缓解：T-12-00-01 — 所有断言均使用 Queue::fake()；CI 绝不执行真实 composer 子进程。
 */

it('PluginManager::uninstall 分发 ComposerRemoveJob（MKTPLACE-06）', function () {
    $this->markTestIncomplete('MKTPLACE-06: ComposerRemoveJob implemented in Wave 2');

    Queue::fake();

    $plugin = Plugin::factory()->create([
        'slug'         => 'test-remove-plugin',
        'package_name' => 'test/remove-plugin',
        'is_enabled'   => true,
        'init_status'  => 'done',
    ]);

    /** @var \App\Services\PluginManager $manager */
    $manager = app(\App\Services\PluginManager::class);
    $manager->uninstall($plugin);

    // 断言：Job 已被推入队列
    Queue::assertPushed(\FilamentAdmin\Jobs\ComposerRemoveJob::class, function ($job) use ($plugin) {
        return $job->pluginId === $plugin->id;
    });
});

it('uninstall 先将插件 disable 再分发移除 Job（MKTPLACE-06 Pitfall 5）', function () {
    $this->markTestIncomplete('MKTPLACE-06: uninstall safety order implemented in Wave 2');

    Queue::fake();

    $plugin = Plugin::factory()->create([
        'slug'         => 'test-disable-before-remove',
        'package_name' => 'test/disable-before-remove',
        'is_enabled'   => true,
        'init_status'  => 'done',
    ]);

    /** @var \App\Services\PluginManager $manager */
    $manager = app(\App\Services\PluginManager::class);
    $manager->uninstall($plugin);

    // 断言：uninstall() 同步执行 disable（is_enabled=false），然后分发 Job
    // 防止 class-not-found 崩溃（RESEARCH Pitfall 5）
    $plugin->refresh();
    expect($plugin->is_enabled)->toBeFalse();

    Queue::assertPushed(\FilamentAdmin\Jobs\ComposerRemoveJob::class);
});

it('ComposerRemoveJob 执行后 plugins 记录被软删除且 optimize:clear 已运行（MKTPLACE-06）', function () {
    $this->markTestIncomplete('MKTPLACE-06: ComposerRemoveJob handle() implemented in Wave 2');

    $plugin = Plugin::factory()->create([
        'slug'         => 'test-remove-done',
        'package_name' => 'test/remove-done',
        'is_enabled'   => false,
        'init_status'  => 'done',
    ]);

    $pluginId = $plugin->id;

    // Wave 2 实现：ComposerRemoveJob::handle() 使用 Process double 模拟成功
    $job = new \FilamentAdmin\Jobs\ComposerRemoveJob($pluginId);
    $job->handle();

    // 断言：记录已被删除（软删除）
    expect(Plugin::find($pluginId))->toBeNull();
    expect(Plugin::withTrashed()->find($pluginId))->not->toBeNull();
});
