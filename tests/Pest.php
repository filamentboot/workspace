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
