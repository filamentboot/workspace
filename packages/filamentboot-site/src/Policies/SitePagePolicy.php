<?php

namespace Filamentboot\FilamentbootSite\Policies;

use Filamentboot\Policies\BasePolicy;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;

/**
 * 静态页面权限策略
 *
 * 继承 BasePolicy，自动推导权限点：
 * - view_any_site_page / view_site_page
 * - create_site_page / update_site_page / delete_site_page
 * - restore_site_page / force_delete_site_page
 *
 * 另有两个 BasePolicy 没有的动作，在此显式覆写：
 * - publish  发布 / 定时发布（#14），与 update 分开，内容编辑只能提交审核
 * - rollback 回滚到历史版本（#15），它整体改写正文，不该等同于普通编辑
 *
 * 覆写只需加方法不需改前缀：BasePolicy 从短类名推导出 site_page。
 * 超管沿用主包 Gate::before()，不进 Policy。
 */
class SitePagePolicy extends BasePolicy
{
    /**
     * 发布权限（#14）
     *
     * Filament 的 Action->authorize('publish') 会走到这里。BasePolicy 没有
     * publish 方法，若不覆写，Gate 对任何非超管一律拒绝——发布按钮永远点不动。
     */
    public function publish(Authenticatable $user, Model $model): bool
    {
        return $user->can("publish_{$this->resourceName()}");
    }

    /**
     * 版本回滚权限（#15）
     */
    public function rollback(Authenticatable $user, Model $model): bool
    {
        return $user->can("rollback_{$this->resourceName()}");
    }
}
