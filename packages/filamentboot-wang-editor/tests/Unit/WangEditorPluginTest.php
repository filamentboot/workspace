<?php

namespace Filamentboot\FilamentbootWangEditor\Tests\Unit;

use Filament\Contracts\Plugin;
use Filamentboot\FilamentbootWangEditor\WangEditorPlugin;
use Filamentboot\FilamentbootWangEditor\WangEditorServiceProvider;
use Illuminate\Foundation\Application;
use Orchestra\Testbench\TestCase;

/**
 * WangEditorPlugin 单元测试
 *
 * 验证：
 * - plugin_id 与 extra.filamentboot.slug 保持一致（'filamentboot-wang-editor'）
 * - WangEditorPlugin 实现 Filament\Contracts\Plugin 接口
 * - ServiceProvider boot() 在无额外依赖时不抛异常
 */
class WangEditorPluginTest extends TestCase
{
    /**
     * 注册包服务提供者
     *
     * @param  Application  $app
     * @return list<class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [WangEditorServiceProvider::class];
    }

    /**
     * 验证插件 ID 与 extra.filamentboot.slug 保持一致（D-09-03/D-09-04）
     *
     * WangEditorPlugin::getId() 必须返回 'filamentboot-wang-editor'，
     * 与 composer.json 中的 extra.filamentboot.slug 字段一致，
     * 确保 plugin:scan 能正确索引并启停插件。
     */
    public function test_plugin_id(): void
    {
        $plugin = WangEditorPlugin::make();

        self::assertSame('filamentboot-wang-editor', $plugin->getId());
    }

    /**
     * 验证 WangEditorPlugin 实现 Filament\Contracts\Plugin 接口
     *
     * plugin:scan 通过 is_a($pluginClass, Plugin::class, true) 验证插件合法性，
     * 必须实现 Plugin 接口方能被启停（D-09-04）。
     */
    public function test_implements_plugin_contract(): void
    {
        $plugin = WangEditorPlugin::make();

        self::assertInstanceOf(Plugin::class, $plugin);
    }
}
