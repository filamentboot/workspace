<?php

namespace LaravelStack\FilamentAdminSite\Policies;

use FilamentAdmin\Policies\BasePolicy;

/**
 * 询盘权限策略（T-10-03-04 PII 保护）
 *
 * 继承 BasePolicy，自动推导权限点：
 * - view_any_contact_message / view_contact_message
 * - update_contact_message（状态流转）
 *
 * create 权限点不使用（前台 Livewire 直接写 DB，后台 canCreate=false）。
 */
class ContactMessagePolicy extends BasePolicy {}
