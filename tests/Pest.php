<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * 为 Feature 测试配置基础测试用例和 RefreshDatabase trait
 */
uses(
    TestCase::class,
    RefreshDatabase::class,
)->in('Feature');

/**
 * 为 Unit 测试配置基础测试用例（含 RefreshDatabase 以支持模型工厂测试）
 */
uses(
    TestCase::class,
    RefreshDatabase::class,
)->in('Unit');

/**
 * 全局辅助函数：验证数据库中是否存在符合所有条件的记录
 *
 * @param  string  $table  表名
 * @param  array<string, mixed>  $data  查询条件数组
 */
function assertDatabaseHasInOrder(string $table, array $data): void
{
    $query = DB::table($table);
    foreach ($data as $key => $value) {
        $query->where($key, $value);
    }
    expect($query->exists())->toBeTrue();
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
function fakePackagistSearch(): array
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
