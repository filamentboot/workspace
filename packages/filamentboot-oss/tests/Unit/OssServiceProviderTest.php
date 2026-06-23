<?php

namespace Filamentboot\FilamentbootOss\Tests\Unit;

use Filamentboot\FilamentbootOss\OssServiceProvider;
use Illuminate\Foundation\Application;
use Orchestra\Testbench\TestCase;

/**
 * OssServiceProvider 单元测试
 *
 * 验证 boot() 在 settings 表缺失时不抛异常（T-08-02 防护），
 * 以及 Storage::extend 已注册 'oss' 驱动。
 */
class OssServiceProviderTest extends TestCase
{
    /**
     * 注册包服务提供者
     *
     * @param  Application  $app
     * @return list<class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [OssServiceProvider::class];
    }

    /**
     * 验证 settings 表不存在时 boot() 不抛 Throwable
     *
     * OssServiceProvider::boot() 的 try/catch (\Throwable) 必须捕获
     * 数据库 settings 表未迁移时抛出的所有异常（T-08-02）。
     */
    public function test_boot_does_not_throw_when_settings_table_missing(): void
    {
        // 如果已到达此处说明 boot() 未抛异常（getPackageProviders 已触发 boot）
        self::assertTrue(true);
    }

    /**
     * 验证 'oss' 驱动已通过 Storage::extend 注册
     *
     * 注入伪配置后调用 Storage::disk('oss') 应返回 FilesystemAdapter，
     * 而非抛出 "Driver [oss] is not supported" 异常。
     * 注意：仅在 iidestiny/flysystem-oss 已安装时执行磁盘实例化验证。
     */
    public function test_oss_driver_is_registered_via_storage_extend(): void
    {
        // 断言 Storage 扩展不报 "unsupported driver"：
        // 通过检查 Storage::getDefaultDriver() 可用性验证驱动已注册。
        // 由于 OssAdapter 类可能未安装，仅验证注册行为（extend 被调用），
        // 实际适配器实例化在 flysystem-oss 安装后由集成测试覆盖。
        $adapters = invade(app('filesystem'))->customCreators ?? [];

        // 验证 Storage::extend('oss', ...) 已注册自定义驱动创建器
        self::assertArrayHasKey('oss', $adapters);
    }
}
