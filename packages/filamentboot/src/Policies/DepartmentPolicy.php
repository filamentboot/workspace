<?php

namespace FilamentAdmin\Policies;

use Illuminate\Contracts\Auth\Authenticatable;

/**
 * 部门组织 Policy
 */
class DepartmentPolicy extends BasePolicy
{
    /**
     * 拖拽排序权限
     */
    public function reorder(Authenticatable $user): bool
    {
        return $user->can('reorder_department');
    }
}
