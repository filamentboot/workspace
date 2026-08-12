<?php

namespace Filamentboot\Tests\Feature\Plugins;

use Filamentboot\FilamentbootServiceProvider;
use Filamentboot\Services\PackagistService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Orchestra\Testbench\TestCase;

/**
 * PackagistService 测试（MKTPLACE-08）
 *
 * 覆盖场景：
 * 1. searchFilamentPlugins 使用 Http::fake 正常返回 results 数组
 * 2. getPackageConstraint 使用 p2 端点 Http::fake 返回 filament/filament 约束字符串
 * 3. HTTP 非 200 响应时返回空兜底结构（{results:[], total:0, next:null}）
 * 4. 搜索结果被 Cache 缓存，二次调用不重复发 HTTP 请求
 *
 * 威胁缓解：T-12-00-02 — Http::fake；CI 绝不命中真实 packagist.org。
 * RESEARCH Pattern 3：Packagist 搜索 + p2 端点。
 */
class PackagistServiceTest extends TestCase
{
    /**
     * @return list<class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [
            FilamentbootServiceProvider::class,
        ];
    }

    /**
     * Packagist 搜索 API fake 响应（MKTPLACE-08 / Wave 0 shared fixture）
     *
     * 返回符合 packagist.org/search.json 文档结构的 fake 数据：
     * { results: [{name, description, url, repository, downloads, favers}], total: INT, next: URL|null }
     *
     * 来源：RESEARCH §Code Examples "Packagist 搜索结果字段完整示例"（实测验证）
     *
     * @return array{results: list<array{name: string, description: string, url: string, repository: string, downloads: int, favers: int}>, total: int, next: string|null}
     */
    private function fakePackagistSearch(): array
    {
        return [
            'results' => [
                [
                    'name'        => 'bezhansalleh/filament-shield',
                    'description' => 'Filament support for `spatie/laravel-permission`.',
                    'url'         => 'https://packagist.org/packages/bezhansalleh/filament-shield',
                    'repository'  => 'https://github.com/bezhanSalleh/filament-shield',
                    'downloads'   => 3712246,
                    'favers'      => 2785,
                ],
                [
                    'name'        => 'awcodes/filament-tiptap-editor',
                    'description' => 'A Tiptap integration for Filament Forms.',
                    'url'         => 'https://packagist.org/packages/awcodes/filament-tiptap-editor',
                    'repository'  => 'https://github.com/awcodes/filament-tiptap-editor',
                    'downloads'   => 1250000,
                    'favers'      => 980,
                ],
            ],
            'total' => 1445,
            'next'  => 'https://packagist.org/search.json?q=&page=2&tags%5B0%5D=filament&per_page=15',
        ];
    }

    /**
     * searchFilamentPlugins 返回 results 数组（MKTPLACE-08）
     */
    public function test_search_filament_plugins_returns_results_array(): void
    {
        Cache::flush();

        Http::fake([
            'packagist.org/search.json*' => Http::response($this->fakePackagistSearch(), 200),
        ]);

        /** @var PackagistService $service */
        $service = app(PackagistService::class);
        $result  = $service->searchFilamentPlugins();

        $this->assertArrayHasKey('results', $result);
        $this->assertIsArray($result['results']);
        $this->assertNotEmpty($result['results']);
        $this->assertGreaterThan(0, $result['total']);
    }

    /**
     * getPackageConstraint 通过 p2 端点返回 filament/filament 约束（MKTPLACE-08）
     */
    public function test_get_package_constraint_via_p2_endpoint(): void
    {
        Cache::flush();

        Http::fake([
            'repo.packagist.org/p2/bezhansalleh/filament-shield.json' => Http::response([
                'packages' => [
                    'bezhansalleh/filament-shield' => [
                        [
                            'version'            => '4.0.0',
                            'version_normalized' => '4.0.0.0',
                            'require'            => [
                                'filament/filament' => '^5.0',
                                'php'               => '^8.3',
                            ],
                        ],
                    ],
                ],
            ], 200),
        ]);

        /** @var PackagistService $service */
        $service = app(PackagistService::class);
        $result  = $service->getPackageConstraint('bezhansalleh/filament-shield');

        $this->assertSame('^5.0', $result);
    }

    /**
     * HTTP 非 200 时 searchFilamentPlugins 返回空兜底结构（MKTPLACE-08）
     */
    public function test_search_filament_plugins_returns_empty_fallback_on_non_200(): void
    {
        Cache::flush();

        Http::fake([
            'packagist.org/search.json*' => Http::response('Service Unavailable', 503),
        ]);

        /** @var PackagistService $service */
        $service = app(PackagistService::class);
        $result  = $service->searchFilamentPlugins();

        // 兜底结构：{results:[], total:0, next:null}
        $this->assertArrayHasKey('results', $result);
        $this->assertEmpty($result['results']);
        $this->assertSame(0, $result['total']);
        $this->assertNull($result['next']);
    }

    /**
     * searchFilamentPlugins 结果被缓存，相同参数二次调用不再发 HTTP（MKTPLACE-08）
     */
    public function test_search_filament_plugins_results_are_cached(): void
    {
        Cache::flush();

        Http::fake([
            'packagist.org/search.json*' => Http::response($this->fakePackagistSearch(), 200),
        ]);

        /** @var PackagistService $service */
        $service = app(PackagistService::class);

        // 首次调用
        $service->searchFilamentPlugins(1, 15);

        // 第二次调用（相同参数）
        $service->searchFilamentPlugins(1, 15);

        // 只发出 1 次 HTTP 请求（第二次命中缓存）
        Http::assertSentCount(1);
    }
}
