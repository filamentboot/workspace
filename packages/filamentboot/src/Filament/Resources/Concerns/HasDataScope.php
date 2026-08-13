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
 * personal 档默认要求模型表有 created_by 列（列名可通过 personalScopeColumn()
 * 覆盖，例如询盘的属主列是 assigned_to 而非 created_by）；department 档要求
 * 模型表有 department_id 列。列均不由本 trait 创建，接入的 Resource 自己迁移。
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
     * personal 档比对的属主列名，默认 created_by
     */
    protected static function personalScopeColumn(): string
    {
        return 'created_by';
    }

    /**
     * personal 档是否放行该列为 NULL 的记录（如未分配的询盘线索）
     *
     * 默认不放行：created_by 语义上创建时必定写入，NULL 属异常数据而非
     * 合法状态，不该被所有人看见。属主列语义是"待分配"时（如
     * ContactMessageResource 的 assigned_to）才应覆盖为 true——否则新记录
     * 在被分配之前，除超管外谁都看不到，比不接入数据权限还糟。
     */
    protected static function personalScopeAllowsUnassigned(): bool
    {
        return false;
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
            'personal'   => static::applyPersonalDataScope($query, $user),
            'department' => static::applyDepartmentDataScope($query, $user),
            default      => $query,
        };
    }

    /**
     * personal 档：属主列命中当前用户，或（按需）该列为 NULL 的未分配记录
     */
    private static function applyPersonalDataScope(Builder $query, AdminUser $user): Builder
    {
        $column = static::personalScopeColumn();

        return $query->where(function (Builder $query) use ($column, $user) {
            $query->where($column, $user->id);

            if (static::personalScopeAllowsUnassigned()) {
                $query->orWhereNull($column);
            }
        });
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
