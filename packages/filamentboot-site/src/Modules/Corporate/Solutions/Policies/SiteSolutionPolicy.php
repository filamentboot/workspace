<?php

namespace Filamentboot\FilamentbootSite\Modules\Corporate\Solutions\Policies;

use Filamentboot\Policies\BasePolicy;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;

/**
 * 智能方案权限策略
 *
 * 继承 BasePolicy，自动推导权限点：
 * - view_any_site_solution / view_site_solution
 * - create_site_solution / update_site_solution / delete_site_solution
 * - restore_site_solution / force_delete_site_solution
 *
 * publish 覆写同 SitePagePolicy（批次 1.5a）：与 update 分开，内容编辑
 * 只能提交审核，发布是独立的一道权责。rollback（批次 1.5c）同理：
 * 整体改写正文，不该等同于普通编辑。
 */
class SiteSolutionPolicy extends BasePolicy
{
    /**
     * 发布权限（批次 1.5a）
     */
    public function publish(Authenticatable $user, Model $model): bool
    {
        return $user->can("publish_{$this->resourceName()}");
    }

    /**
     * 版本回滚权限（批次 1.5c）
     */
    public function rollback(Authenticatable $user, Model $model): bool
    {
        return $user->can("rollback_{$this->resourceName()}");
    }
}
