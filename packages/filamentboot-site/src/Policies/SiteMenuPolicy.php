<?php

namespace Filamentboot\FilamentbootSite\Policies;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;

/**
 * 前台导航菜单权限策略（#17）
 *
 * 不继承 BasePolicy：菜单只有「能不能管」这一档，不需要
 * view_any / view / create / update / delete 五个独立权限点——
 * 能改导航结构的人一定也能新建和删除菜单项，拆开只是给角色配置添麻烦。
 *
 * 因此全部动作统一收敛到 manage_site_menu。SiteMenuItemPolicy 用同一个权限点：
 * 菜单与菜单项在使用上是一件事，分开授权会出现「能建菜单但改不了菜单项」的死角。
 *
 * 由 Laravel 的约定发现解析（Models\SiteMenu → Policies\SiteMenuPolicy），
 * 与本包其余 Policy 一致，不需要显式 Gate::policy()。
 * 超管沿用主包 Gate::before()，不进 Policy。
 */
class SiteMenuPolicy
{
    /**
     * 管理菜单所需的权限点
     */
    protected const PERMISSION = 'manage_site_menu';

    public function viewAny(Authenticatable $user): bool
    {
        return $user->can(static::PERMISSION);
    }

    public function view(Authenticatable $user, Model $model): bool
    {
        return $user->can(static::PERMISSION);
    }

    public function create(Authenticatable $user): bool
    {
        return $user->can(static::PERMISSION);
    }

    public function update(Authenticatable $user, Model $model): bool
    {
        return $user->can(static::PERMISSION);
    }

    public function delete(Authenticatable $user, Model $model): bool
    {
        return $user->can(static::PERMISSION);
    }

    public function deleteAny(Authenticatable $user): bool
    {
        return $user->can(static::PERMISSION);
    }
}
