<?php

namespace Filamentboot\FilamentbootSite\Modules\Corporate\Cases\Policies;

use Filamentboot\Policies\BasePolicy;

/**
 * 案例分类权限策略
 *
 * 继承 BasePolicy，权限点由 class_basename 推导（与命名空间深度无关）：
 * - view_any_site_case_category / view_site_case_category
 * - create_site_case_category / update_site_case_category / delete_site_case_category
 */
class SiteCaseCategoryPolicy extends BasePolicy {}
