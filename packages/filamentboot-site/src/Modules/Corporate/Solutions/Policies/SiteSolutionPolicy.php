<?php

namespace Filamentboot\FilamentbootSite\Modules\Corporate\Solutions\Policies;

use Filamentboot\Policies\BasePolicy;

/**
 * 智能方案权限策略
 *
 * 继承 BasePolicy，自动推导权限点：
 * - view_any_site_solution / view_site_solution
 * - create_site_solution / update_site_solution / delete_site_solution
 * - restore_site_solution / force_delete_site_solution
 */
class SiteSolutionPolicy extends BasePolicy {}
