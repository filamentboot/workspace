<?php

/**
 * filament-impersonate 横幅翻译覆盖（en locale 应用时生效）
 *
 * APP_LOCALE=en 时覆盖插件默认英文，统一展示中文横幅（D-19）：
 * "正在模拟 {username}（结束模拟）"
 *
 * 由 FilamentAdminServiceProvider::registerTranslations() 加载到
 * filament-impersonate 命名空间。
 */
return [
    'impersonating' => '正在模拟',
    'leave'         => '结束模拟',
];
