<?php

namespace Filamentboot\FilamentbootSite\Modules\Corporate\Packages\Policies;

use Filamentboot\Policies\BasePolicy;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;

/**
 * 全屋智能套餐权限策略
 *
 * 继承 BasePolicy，自动推导权限点：
 * - view_any_site_package / view_site_package
 * - create_site_package / update_site_package / delete_site_package
 * - restore_site_package / force_delete_site_package
 *
 * publish 覆写同 SitePagePolicy（批次 1.5a）：与 update 分开，内容编辑
 * 只能提交审核，发布是独立的一道权责。rollback（批次 1.5c）同理：
 * 整体改写正文，不该等同于普通编辑。
 */
class SitePackagePolicy extends BasePolicy
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
