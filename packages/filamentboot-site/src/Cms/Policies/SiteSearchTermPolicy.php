<?php

namespace Filamentboot\FilamentbootSite\Cms\Policies;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;

/**
 * 站内搜索词权限策略
 *
 * 只有「能不能看」这一档，收敛到 view_site_search_terms。
 *
 * **没有 create 与 update**：这张表只由前台搜索行为写入，后台手工造一条
 * 或改掉次数，等于伪造运营数据——它存在的全部价值就是「真实发生过」。
 * delete 保留，用于清掉压测或明显是爬虫留下的噪声词。
 *
 * 由 Laravel 的约定发现解析（Cms\Models\SiteSearchTerm → Cms\Policies\SiteSearchTermPolicy）。
 */
class SiteSearchTermPolicy
{
    /**
     * 查看搜索词所需的权限点
     */
    protected const PERMISSION = 'view_site_search_terms';

    public function viewAny(Authenticatable $user): bool
    {
        return $user->can(static::PERMISSION);
    }

    public function view(Authenticatable $user, Model $model): bool
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
