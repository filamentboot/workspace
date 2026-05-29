<?php

namespace App\Policies;

/**
 * 管理员用户 Policy
 *
 * 权限点前缀为 admin_user（由 BasePolicy::resourceName() 自动推断）。
 * 完整权限点：view_any_admin_user / view_admin_user / create_admin_user / ...
 */
class AdminUserPolicy extends BasePolicy
{
    // 全部继承自 BasePolicy，无需重写
}
