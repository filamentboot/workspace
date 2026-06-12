<?php

namespace FilamentAdmin\Filament\Resources\Concerns;

use FilamentAdmin\Services\ActivityLogger;
use Illuminate\Database\Eloquent\Model;

/**
 * 为树形可排序资源提供排序日志记录能力
 *
 * 使用此 Trait 的资源需具备：
 * - Filament 的 reorderable() 表格配置
 * - 模型具有 id、parent_id、name、sort、is_active 等字段（面向树形可排序资源）
 * - 在 table() 的 beforeReordering 回调中调用 static::rememberReorderSnapshot()
 * - 在 table() 的 afterReordering 回调中调用 static::logReorderActivity()
 */
trait ReorderableWithLog
{
    /**
     * 缓存排序前快照
     *
     * @param  array<int, string|int>  $order  排序前的记录 ID 列表
     */
    protected static function rememberReorderSnapshot(array $order): void
    {
        request()->attributes->set(
            static::getModel().'_reorder_before',
            static::buildReorderSnapshot($order)
        );
    }

    /**
     * 记录排序操作日志
     *
     * @param  array<int, string|int>  $order  排序后的记录 ID 列表
     */
    protected static function logReorderActivity(array $order): void
    {
        $logger = app(ActivityLogger::class);
        $causer = $logger->currentCauser();
        /** @var Model|null $record */
        $record = static::getModel()::query()->find($order[0] ?? null);

        if (! $causer || ! $record) {
            return;
        }

        $before = request()->attributes->get(static::getModel().'_reorder_before', []);
        $after  = static::buildReorderSnapshot($order);

        $logger->logChanges(
            causer: $causer,
            subject: $record,
            action: 'reordered',
            before: ['order' => $before],
            after: ['order' => $after],
        );
    }

    /**
     * 构建排序快照
     *
     * 快照列集合 ['id', 'parent_id', 'name', 'sort', 'is_active'] 面向树形可排序资源。
     *
     * @param  array<int, string|int>  $order  记录 ID 列表
     * @return array<int, array<string, mixed>>
     */
    protected static function buildReorderSnapshot(array $order): array
    {
        return static::getModel()::query()
            ->whereKey($order)
            ->orderBy('sort')
            ->get(['id', 'parent_id', 'name', 'sort', 'is_active'])
            ->map(fn (Model $item): array => [
                'id'        => $item->id,
                'parent_id' => $item->parent_id,
                'name'      => $item->name,
                'sort'      => $item->sort,
                'is_active' => $item->is_active,
            ])
            ->values()
            ->all();
    }
}
