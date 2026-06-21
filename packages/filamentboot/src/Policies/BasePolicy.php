<?php

namespace FilamentAdmin\Policies;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;

/**
 * 基础 Policy 抽象类
 *
 * 所有 Resource Policy 继承此类，默认实现检查 Spatie Permission 权限点。
 * 权限点命名格式：{action}_{resource_snake_case}
 *
 * 例如 AdminUserPolicy 的 viewAny 检查 view_any_admin_user 权限点。
 *
 * 命名格式与 Filament Shield 4.x 配置严格对齐：
 *   config('filament-shield.permissions.case')      = 'snake'
 *   config('filament-shield.permissions.separator') = '_'
 *
 * 超级管理员通过 Gate::before（AuthServiceProvider）拦截，不会进入 Policy。
 */
abstract class BasePolicy
{
    /**
     * 获取资源名称（用于权限点拼接）
     *
     * 子类可覆盖此方法自定义权限点前缀，默认从类名推断：
     * AdminUserPolicy -> admin_user
     * LoginLogPolicy  -> login_log
     */
    protected function resourceName(): string
    {
        $class = class_basename(static::class);

        return str($class)->replaceLast('Policy', '')->snake()->value();
    }

    /**
     * 查看列表权限
     */
    public function viewAny(Authenticatable $user): bool
    {
        return $user->can("view_any_{$this->resourceName()}");
    }

    /**
     * 查看单条记录权限
     */
    public function view(Authenticatable $user, Model $model): bool
    {
        return $user->can("view_{$this->resourceName()}");
    }

    /**
     * 创建权限
     */
    public function create(Authenticatable $user): bool
    {
        return $user->can("create_{$this->resourceName()}");
    }

    /**
     * 更新权限
     */
    public function update(Authenticatable $user, Model $model): bool
    {
        return $user->can("update_{$this->resourceName()}");
    }

    /**
     * 删除权限
     */
    public function delete(Authenticatable $user, Model $model): bool
    {
        return $user->can("delete_{$this->resourceName()}");
    }

    /**
     * 批量删除权限
     */
    public function deleteAny(Authenticatable $user): bool
    {
        return $user->can("delete_any_{$this->resourceName()}");
    }

    /**
     * 恢复软删除权限
     */
    public function restore(Authenticatable $user, Model $model): bool
    {
        return $user->can("restore_{$this->resourceName()}");
    }

    /**
     * 批量恢复权限
     */
    public function restoreAny(Authenticatable $user): bool
    {
        return $user->can("restore_any_{$this->resourceName()}");
    }

    /**
     * 强制删除权限
     */
    public function forceDelete(Authenticatable $user, Model $model): bool
    {
        return $user->can("force_delete_{$this->resourceName()}");
    }

    /**
     * 批量强制删除权限
     */
    public function forceDeleteAny(Authenticatable $user): bool
    {
        return $user->can("force_delete_any_{$this->resourceName()}");
    }
}
