<?php

namespace Filamentboot\Tests\Unit\Plugins;

use Filamentboot\FilamentbootServiceProvider;
use Filamentboot\Services\MarketplaceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Orchestra\Testbench\TestCase;
use Spatie\Activitylog\ActivitylogServiceProvider;
use Spatie\LaravelSettings\LaravelSettingsServiceProvider;
use Spatie\Permission\PermissionServiceProvider;

/**
 * 市场服务测试（PLUGIN-07）
 *
 * 覆盖：fetchIndex 命中缓存不写 plugins 表、HTTP 失败兜底本地 config
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
class MarketplaceServiceTest extends TestCase
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

    /**
     * fetchIndex 命中缓存时不写 plugins 表
     */
    public function test_fetch_index_does_not_write_plugins_table_when_cache_hit(): void
    {
        Cache::flush();

        // 预热缓存
        Cache::put('market.index', [['slug' => 'cached-plugin']], 300);

        Http::fake(); // 防止真实 HTTP 调用

        $service = new MarketplaceService;
        $result  = $service->fetchIndex();

        // 命中缓存，不触发 HTTP
        Http::assertNothingSent();

        // 浏览不写 plugins 表（D-06-06/07）
        $this->assertNotEmpty($result);
        $this->assertDatabaseCount('plugins', 0);
    }

    /**
     * fetchIndex HTTP 回源时仍不写 plugins 表
     */
    public function test_fetch_index_does_not_write_plugins_table_on_http_fallback(): void
    {
        Cache::flush();

        Http::fake([
            '*' => Http::response([
                'entries' => [
                    ['slug' => 'remote-plugin-1'],
                    ['slug' => 'remote-plugin-2'],
                ],
            ], 200),
        ]);

        $service = new MarketplaceService;
        $result  = $service->fetchIndex();

        // 返回远程数据
        $this->assertCount(2, $result);
        $this->assertSame('remote-plugin-1', $result[0]['slug']);

        // 浏览不写 plugins 表（D-06-06/07）
        $this->assertDatabaseCount('plugins', 0);
    }

    /**
     * fetchIndex HTTP 失败时返回本地兜底配置
     */
    public function test_fetch_index_returns_local_fallback_config_on_http_failure(): void
    {
        Cache::flush();

        Http::fake([
            '*' => Http::response('Server Error', 500),
        ]);

        $service = new MarketplaceService;
        $result  = $service->fetchIndex();

        // 兜底 config('official-market.entries') 有 6 条
        $this->assertCount(6, $result);

        // 浏览不写 plugins 表
        $this->assertDatabaseCount('plugins', 0);
    }
}
