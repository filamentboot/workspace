<?php

namespace Filamentboot\FilamentbootSite\Cms\Policies;

/**
 * 前台导航菜单项权限策略（#17）
 *
 * 与 SiteMenuPolicy 共用 manage_site_menu：菜单与菜单项在使用上是一件事，
 * 分开授权会出现「能建菜单但改不了菜单项」的死角。
 */
class SiteMenuItemPolicy extends SiteMenuPolicy {}
