<?php

namespace Filamentboot\FilamentbootSite\Policies;

use Filamentboot\Policies\BasePolicy;

/**
 * 智能产品权限策略
 *
 * 继承 BasePolicy，自动推导权限点：
 * - view_any_site_product / view_site_product
 * - create_site_product / update_site_product / delete_site_product
 * - restore_site_product / force_delete_site_product
 */
class SiteProductPolicy extends BasePolicy {}
