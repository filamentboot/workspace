<?php

namespace Filamentboot\FilamentbootSite\Modules\Corporate\Products\Policies;

use Filamentboot\Policies\BasePolicy;

/**
 * 产品分类权限策略
 *
 * 继承 BasePolicy，权限点由 class_basename 推导（与命名空间深度无关）：
 * - view_any_site_product_category / view_site_product_category
 * - create_site_product_category / update_site_product_category / delete_site_product_category
 */
class SiteProductCategoryPolicy extends BasePolicy {}
