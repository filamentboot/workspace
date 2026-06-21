<?php

namespace Filamentboot\FilamentbootSite\Policies;

use Filamentboot\Policies\BasePolicy;

/**
 * 装修案例权限策略
 *
 * 继承 BasePolicy，自动推导权限点：
 * - view_any_site_case / view_site_case
 * - create_site_case / update_site_case / delete_site_case
 * - restore_site_case / force_delete_site_case
 */
class SiteCasePolicy extends BasePolicy {}
