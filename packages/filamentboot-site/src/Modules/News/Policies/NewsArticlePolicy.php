<?php

namespace Filamentboot\FilamentbootSite\Modules\News\Policies;

use Filamentboot\Policies\BasePolicy;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;

/**
 * 资讯文章权限策略
 *
 * 继承 BasePolicy，权限点由 class_basename 推导（与命名空间深度无关）：
 * - view_any_news_article / view_news_article
 * - create_news_article / update_news_article / delete_news_article
 * - restore_news_article / force_delete_news_article
 *
 * publish 覆写同 SitePagePolicy（批次 1.5a）：与 update 分开，内容编辑
 * 只能提交审核，发布是独立的一道权责。rollback（批次 1.5c）同理：
 * 整体改写正文，不该等同于普通编辑。
 */
class NewsArticlePolicy extends BasePolicy
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
