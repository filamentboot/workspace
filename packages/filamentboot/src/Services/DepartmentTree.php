<?php

namespace FilamentAdmin\Services;

use FilamentAdmin\Models\Department;

/**
 * 部门树服务
 */
class DepartmentTree
{
    /**
     * 获取下级部门 ID
     *
     * @return list<int>
     */
    public function getDescendantIds(Department $department): array
    {
        $descendantIds = [];
        $parentIds     = [$department->id];

        while (true) {
            $childrenIds = Department::query()
                ->whereIn('parent_id', $parentIds)
                ->pluck('id')
                ->map(fn (mixed $id): int => (int) $id)
                ->all();

            if ($childrenIds === []) {
                break;
            }

            $descendantIds = [...$descendantIds, ...$childrenIds];
            $parentIds     = $childrenIds;
        }

        return array_values(array_unique($descendantIds));
    }

    /**
     * 获取当前部门及下级部门 ID
     *
     * @return list<int>
     */
    public function getSelfAndDescendantIds(Department $department): array
    {
        return [
            $department->id,
            ...$this->getDescendantIds($department),
        ];
    }

    /**
     * 判断调整父级后是否会形成循环
     */
    public function wouldCreateCycle(Department $department, ?Department $parent): bool
    {
        if (! $parent) {
            return false;
        }

        if ($department->is($parent)) {
            return true;
        }

        return in_array($parent->id, $this->getDescendantIds($department), true);
    }
}
