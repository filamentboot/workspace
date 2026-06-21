<?php

namespace FilamentAdmin\Policies;

use Illuminate\Contracts\Auth\Authenticatable;

/**
 * 菜单规则 Policy
 */
class MenuPolicy extends BasePolicy
{
    /**
     * 拖拽排序权限
     */
    public function reorder(Authenticatable $user): bool
    {
        return $user->can('reorder_menu');
    }
}
