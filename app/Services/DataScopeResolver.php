<?php

namespace App\Services;

use App\Enums\DataScope;
use App\Models\AdminUser;
use App\Models\Department;
use App\Models\RoleDataScope;
use Spatie\Permission\Models\Role;

/**
 * 数据权限解析服务
 */
class DataScopeResolver
{
    public function __construct(
        protected DepartmentTree $departmentTree
    ) {}

    /**
     * 解析管理员最终数据范围
     *
     * @return array{
     *     is_all: bool,
     *     department_ids: list<int>,
     *     admin_user_ids: list<int>
     * }
     */
    public function resolve(AdminUser $user): array
    {
        if ($user->hasRole(config('filament-admin.super_admin_role'))) {
            return [
                'is_all'         => true,
                'department_ids' => [],
                'admin_user_ids' => [],
            ];
        }

        $user->loadMissing('roles', 'department');

        $scopeByRoleId = RoleDataScope::query()
            ->whereIn('role_id', $user->roles->pluck('id'))
            ->get()
            ->keyBy('role_id');

        if ($scopeByRoleId->contains(fn (RoleDataScope $scope): bool => $scope->scope === DataScope::All)) {
            return [
                'is_all'         => true,
                'department_ids' => [],
                'admin_user_ids' => [],
            ];
        }

        $departmentIds       = [];
        $adminUserIds        = [];
        $needsSelfFallback   = false;
        $resolvedAnyScope    = false;
        $currentDepartmentId = $user->department_id;

        foreach ($user->roles as $role) {
            /** @var Role $role */
            $roleId      = (int) $role->getKey();
            $scopeRecord = $scopeByRoleId->get($roleId);
            $scope       = $scopeRecord instanceof RoleDataScope ? $scopeRecord->scope : DataScope::Self;

            $resolvedAnyScope = true;

            match ($scope) {
                DataScope::Department => $this->pushDepartmentScope(
                    departmentIds: $departmentIds,
                    departmentId: $currentDepartmentId,
                    needsSelfFallback: $needsSelfFallback,
                ),
                DataScope::DepartmentAndChildren => $this->pushDepartmentAndChildrenScope(
                    departmentIds: $departmentIds,
                    department: $user->department,
                    needsSelfFallback: $needsSelfFallback,
                ),
                DataScope::CustomDepartments => $this->pushCustomDepartmentScope(
                    departmentIds: $departmentIds,
                    scope: $scopeRecord instanceof RoleDataScope ? $scopeRecord : null,
                ),
                DataScope::Self => $adminUserIds[] = $user->id,
                default         => null,
            };
        }

        if (! $resolvedAnyScope || ($needsSelfFallback && $departmentIds === [] && $adminUserIds === [])) {
            $adminUserIds[] = $user->id;
        }

        return [
            'is_all'         => false,
            'department_ids' => array_values(array_unique($departmentIds)),
            'admin_user_ids' => array_values(array_unique($adminUserIds)),
        ];
    }

    /**
     * 添加本部门范围
     *
     * @param  list<int>  $departmentIds
     */
    protected function pushDepartmentScope(array &$departmentIds, ?int $departmentId, bool &$needsSelfFallback): void
    {
        if (! $departmentId) {
            $needsSelfFallback = true;

            return;
        }

        $departmentIds[] = $departmentId;
    }

    /**
     * 添加本部门及下级范围
     *
     * @param  list<int>  $departmentIds
     */
    protected function pushDepartmentAndChildrenScope(array &$departmentIds, ?Department $department, bool &$needsSelfFallback): void
    {
        if (! $department) {
            $needsSelfFallback = true;

            return;
        }

        $departmentIds = [
            ...$departmentIds,
            ...$this->departmentTree->getSelfAndDescendantIds($department),
        ];
    }

    /**
     * 添加指定部门范围
     *
     * @param  list<int>  $departmentIds
     */
    protected function pushCustomDepartmentScope(array &$departmentIds, ?RoleDataScope $scope): void
    {
        if (! $scope) {
            return;
        }

        $departmentIds = [
            ...$departmentIds,
            ...array_map(fn (mixed $id): int => (int) $id, $scope->department_ids ?? []),
        ];
    }
}
