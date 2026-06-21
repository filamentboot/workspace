<?php

namespace Filamentboot\FilamentbootRichEditor\Tests\Unit;

use Filamentboot\FilamentbootRichEditor\RichEditorPlugin;
use Filamentboot\FilamentbootRichEditor\RichEditorServiceProvider;
use Orchestra\Testbench\TestCase;

/**
 * RichEditorPlugin 单元测试
 *
 * 验证：
 * - plugin_id 与 slug 保持一致（'filamentboot-rich-editor'）
 * - ServiceProvider boot() 在无额外依赖时不抛异常
 */
class RichEditorPluginTest extends TestCase
{
    /**
     * 注册包服务提供者
     *
     * @param  \Illuminate\Foundation\Application  $app
     * @return list<class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [RichEditorServiceProvider::class];
    }

    /**
     * 验证插件 ID 与 extra.filamentboot.slug 保持一致
     *
     * RichEditorPlugin::getId() 必须返回 'filamentboot-rich-editor'，
     * 与 composer.json 中的 extra.filamentboot.slug 字段一致，
     * 确保 plugin:scan 能正确索引并启停插件。
     */
    public function test_plugin_id_matches_slug(): void
    {
        $plugin = RichEditorPlugin::make();

        self::assertSame('filamentboot-rich-editor', $plugin->getId());
    }

    /**
     * 验证 ServiceProvider boot() 不抛异常
     *
     * RichEditorServiceProvider::boot() 调用 registerRoutes()（09-01 阶段为空实现），
     * 在无任何外部依赖时应正常启动，不抛出任何 Throwable。
     * 如果已到达此处说明 boot() 未抛异常（getPackageProviders 已触发 boot）。
     */
    public function test_boot_does_not_throw(): void
    {
        // 如果已到达此处，说明 getPackageProviders 触发 boot() 成功
        self::assertTrue(true);
    }

    /**
     * 验证 RichEditorPlugin::make() 返回正确类型
     */
    public function test_make_returns_plugin_instance(): void
    {
        $plugin = RichEditorPlugin::make();

        self::assertInstanceOf(RichEditorPlugin::class, $plugin);
    }
}
