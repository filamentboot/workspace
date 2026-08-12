<?php

namespace Filamentboot\FilamentbootSite\Policies;

use Filamentboot\Policies\BasePolicy;

/**
 * 标签权限策略
 *
 * 继承 BasePolicy，权限点由 class_basename 推导：
 * - view_any_site_tag / view_site_tag
 * - create_site_tag / update_site_tag / delete_site_tag
 *
 * 比分类高一档的授权敏感度：标签的 slug 就是公开聚合页地址，
 * 改名等于改一批已收录的 URL。
 */
class SiteTagPolicy extends BasePolicy {}
