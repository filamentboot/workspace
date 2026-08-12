<?php

namespace Filamentboot\Policies;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;

/**
 * 插件 Policy
 *
 * 权限点前缀为 plugin（由 BasePolicy::resourceName() 自动推断）。
 * 完整权限点：view_any_plugin / view_plugin / update_plugin / initialize_plugin
 *             install_plugin / uninstall_plugin
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

    /**
     * 安装插件权限（CR-03）
     *
     * PluginResource::install Action 调用 ->authorize('install_plugin')，
     * 对应 Gate::check('install_plugin', $record) → 此方法。
     * 非超级管理员需要具有 install_plugin 权限点（通过 Filament Shield 分配）。
     */
    public function installPlugin(Authenticatable $user, Model $model): bool
    {
        return $user->can('install_plugin');
    }

    /**
     * 卸载插件权限（CR-03）
     *
     * PluginResource::uninstall Action 调用 ->authorize('uninstall_plugin')，
     * 对应 Gate::check('uninstall_plugin', $record) → 此方法。
     */
    public function uninstallPlugin(Authenticatable $user, Model $model): bool
    {
        return $user->can('uninstall_plugin');
    }
}
