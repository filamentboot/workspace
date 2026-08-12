<?php

namespace Filamentboot\FilamentbootSite\Modules\Corporate\Banners\Policies;

use Filamentboot\Policies\BasePolicy;

/**
 * 幻灯片权限策略
 *
 * 继承 BasePolicy，自动推导权限点：
 * - view_any_site_banner / view_site_banner
 * - create_site_banner / update_site_banner / delete_site_banner
 * - restore_site_banner / force_delete_site_banner
 */
class SiteBannerPolicy extends BasePolicy {}
