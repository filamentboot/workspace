<?php

namespace Filamentboot\FilamentbootSite\Policies;

use Filamentboot\Policies\BasePolicy;

/**
 * 静态页面权限策略
 *
 * 继承 BasePolicy，自动推导权限点：
 * - view_any_site_page / view_site_page
 * - create_site_page / update_site_page / delete_site_page
 * - restore_site_page / force_delete_site_page
 */
class SitePagePolicy extends BasePolicy {}
