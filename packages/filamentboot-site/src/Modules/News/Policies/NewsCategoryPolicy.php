<?php

namespace Filamentboot\FilamentbootSite\Modules\News\Policies;

use Filamentboot\Policies\BasePolicy;

/**
 * 资讯分类权限策略
 *
 * 继承 BasePolicy，权限点由 class_basename 推导（与命名空间深度无关）：
 * - view_any_news_category / view_news_category
 * - create_news_category / update_news_category / delete_news_category
 */
class NewsCategoryPolicy extends BasePolicy {}
