<?php

namespace Filamentboot\Filament\Resources\Concerns;

use Filamentboot\Models\AdminUser;
use Filamentboot\Services\DepartmentTree;
use Illuminate\Database\Eloquent\Builder;

/**
 * 可选的记录级数据权限分档（personal / department 两档）
 *
 * BasePolicy 只判断“能不能做这个操作”，不判断“能操作哪些行”。需要记录级
 * 过滤的 Resource 在自己的 getEloquentQuery() 里显式调用 applyDataScope()——
 * 不做成覆盖 getEloquentQuery() 本身：已有 13 个 Resource 各自覆写了这个
 * 钩子，trait 若也覆写会被子类实现整个吃掉（做法参照 CreatesRedirectOnSlugChange）。
 *
 * 超级管理员在这里直接放行、不受分档限制，与 FilamentbootServiceProvider
 * 里 Gate::before() 的超管放行语义保持一致——否则超管在 Policy 层什么都能
 * 做，列表页却看不到自己应该能管的记录。
 *
 * personal 档要求模型表有 created_by 列；department 档要求模型表有
 * department_id 列。两列均不由本 trait 创建，接入的 Resource 自己迁移。
 */
trait HasDataScope
{
    /**
     * 数据权限档位，子类覆盖返回 'personal' / 'department'；默认不限制
     */
    protected static function dataScope(): ?string
    {
        return null;
    }

    /**
     * 按 dataScope() 过滤查询结果
     */
    protected static function applyDataScope(Builder $query): Builder
    {
        $scope = static::dataScope();

        if ($scope === null) {
            return $query;
        }

        $user = auth('admin')->user();

        if (! $user instanceof AdminUser) {
            return $query->whereRaw('1 = 0');
        }

        if ($user->hasRole(config('filamentboot.super_admin_role'))) {
            return $query;
        }

        return match ($scope) {
            'personal'   => $query->where('created_by', $user->id),
            'department' => static::applyDepartmentDataScope($query, $user),
            default      => $query,
        };
    }

    /**
     * department 档：本部门及所有子孙部门
     */
    private static function applyDepartmentDataScope(Builder $query, AdminUser $user): Builder
    {
        if (blank($user->department_id)) {
            return $query->whereRaw('1 = 0');
        }

        $ids = app(DepartmentTree::class)->getSelfAndDescendantIds($user->department);

        return $query->whereIn('department_id', $ids);
    }
}
