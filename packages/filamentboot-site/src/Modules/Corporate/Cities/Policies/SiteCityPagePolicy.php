<?php

namespace Filamentboot\FilamentbootSite\Modules\Corporate\Cities\Policies;

use Filamentboot\Policies\BasePolicy;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;

/**
 * 城市页权限策略
 *
 * 继承 BasePolicy，自动推导权限点：
 * - view_any_site_city_page / view_site_city_page
 * - create_site_city_page / update_site_city_page / delete_site_city_page
 * - restore_site_city_page / force_delete_site_city_page
 *
 * publish 覆写同 SitePagePolicy（批次 1.5a）：与 update 分开，内容编辑
 * 只能提交审核，发布是独立的一道权责。rollback（批次 1.5c）同理：
 * 整体改写正文，不该等同于普通编辑。
 *
 * 区划（SiteRegion）没有对应的 Policy：它不进后台，是导入命令的产物。
 */
class SiteCityPagePolicy extends BasePolicy
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
