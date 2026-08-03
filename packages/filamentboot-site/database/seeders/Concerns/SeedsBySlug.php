<?php

namespace Filamentboot\FilamentbootSite\Database\Seeders\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * 按 slug 幂等写入演示内容
 *
 * 为什么不能直接用 Model::firstOrCreate(['slug' => ...])：
 * 内容模型大多带软删除，而 slug 上是普通 unique 索引——它不认 deleted_at。
 * 用户在后台删掉某条演示内容（软删，行还在），再跑一次种子时 firstOrCreate
 * 的查询走默认作用域，看不见那一行，于是执行 INSERT，直接撞 unique 约束 500。
 *
 * 这也是种子此前退而求其次做「整条 Seeder 整体跳过」的原因：宁可不补种，
 * 也不能崩。查询改带 withTrashed() 之后，那条约束冲突不存在了，
 * 种子就能变成真正的增量补种——已有的不动，缺的补上。
 *
 * 软删除过的记录一律原样返回、不复活：用户删它是有意的，
 * 种子不该替用户做恢复决定。调用方用返回对象的 wasRecentlyCreated
 * 区分「这次新建的」与「本来就有的」，决定要不要再动它的关联数据。
 */
trait SeedsBySlug
{
    /**
     * 按 slug 取已有记录，没有才创建
     *
     * @param  class-string<Model>  $model
     * @param  array<string, mixed>  $data  必须含 slug 键
     */
    protected function firstOrCreateBySlug(string $model, array $data): Model
    {
        $query = in_array(SoftDeletes::class, class_uses_recursive($model), true)
            ? $model::withTrashed()
            : $model::query();

        return $query->firstOrCreate(['slug' => $data['slug']], $data);
    }
}
