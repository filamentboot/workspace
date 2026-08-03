<?php

namespace Filamentboot\FilamentbootSite\Modules\News\Policies;

use Filamentboot\Policies\BasePolicy;

/**
 * 资讯文章权限策略
 *
 * 继承 BasePolicy，权限点由 class_basename 推导（与命名空间深度无关）：
 * - view_any_news_article / view_news_article
 * - create_news_article / update_news_article / delete_news_article
 * - restore_news_article / force_delete_news_article
 */
class NewsArticlePolicy extends BasePolicy {}
