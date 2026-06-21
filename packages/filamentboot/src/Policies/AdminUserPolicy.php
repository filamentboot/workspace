<?php

namespace FilamentAdmin\Policies;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;

/**
 * 管理员用户 Policy
 *
 * 权限点前缀为 admin_user（由 BasePolicy::resourceName() 自动推断）。
 * 完整权限点：view_any_admin_user / view_admin_user / create_admin_user / ...
 */
class AdminUserPolicy extends BasePolicy
{
    /**
     * 重置密码权限
     */
    public function resetPassword(Authenticatable $user, Model $model): bool
    {
        return $user->can('reset_password_admin_user');
    }

    /**
     * 分配角色权限
     */
    public function assignRole(Authenticatable $user, Model $model): bool
    {
        return $user->can('assign_role_admin_user');
    }
}
