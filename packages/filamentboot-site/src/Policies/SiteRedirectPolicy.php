<?php

namespace Filamentboot\FilamentbootSite\Policies;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;

/**
 * URL 重定向权限策略（#18）
 *
 * 与 SiteMenuPolicy 同理，只有「能不能管」这一档，全部动作收敛到
 * manage_site_redirect——能建重定向的人一定也能删，拆开只是给角色配置添麻烦。
 *
 * 由 Laravel 的约定发现解析（Models\SiteRedirect → Policies\SiteRedirectPolicy）。
 */
class SiteRedirectPolicy
{
    /**
     * 管理重定向所需的权限点
     */
    protected const PERMISSION = 'manage_site_redirect';

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
