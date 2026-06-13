<?php

namespace LaravelStack\FilamentAdminWangEditor\Tests\Unit;

use Filament\Contracts\Plugin;
use LaravelStack\FilamentAdminWangEditor\WangEditorPlugin;
use LaravelStack\FilamentAdminWangEditor\WangEditorServiceProvider;
use Orchestra\Testbench\TestCase;

/**
 * WangEditorPlugin 单元测试
 *
 * 验证：
 * - plugin_id 与 extra.filament-admin.slug 保持一致（'filament-admin-wang-editor'）
 * - WangEditorPlugin 实现 Filament\Contracts\Plugin 接口
 * - ServiceProvider boot() 在无额外依赖时不抛异常
 */
class WangEditorPluginTest extends TestCase
{
    /**
     * 注册包服务提供者
     *
     * @param  \Illuminate\Foundation\Application  $app
     * @return list<class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [WangEditorServiceProvider::class];
    }

    /**
     * 验证插件 ID 与 extra.filament-admin.slug 保持一致（D-09-03/D-09-04）
     *
     * WangEditorPlugin::getId() 必须返回 'filament-admin-wang-editor'，
     * 与 composer.json 中的 extra.filament-admin.slug 字段一致，
     * 确保 plugin:scan 能正确索引并启停插件。
     */
    public function test_plugin_id(): void
    {
        $plugin = WangEditorPlugin::make();

        self::assertSame('filament-admin-wang-editor', $plugin->getId());
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
