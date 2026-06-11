<?php

namespace App\Policies;

use FilamentAdmin\Policies\BasePolicy;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;

/**
 * 插件 Policy
 *
 * 权限点前缀为 plugin（由 BasePolicy::resourceName() 自动推断）。
 * 完整权限点：view_any_plugin / view_plugin / update_plugin / initialize_plugin
 */
class PluginPolicy extends BasePolicy
{
    /**
     * 初始化插件权限（方案型插件专用）
     */
    public function initialize(Authenticatable $user, Model $model): bool
    {
        return $user->can('initialize_plugin');
    }
}
