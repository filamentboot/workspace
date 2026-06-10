<?php

/**
 * filament-impersonate 横幅翻译覆盖（zh_CN）
 *
 * 覆盖插件默认 zh_CN 翻译，将横幅文案对齐锁定字面：
 * "正在模拟 {username}（结束模拟）"（D-19）。
 * 通过 FilamentAdminServiceProvider::registerTranslations() 加载到
 * filament-impersonate 命名空间，app.locale=zh_CN 时生效。
 */
return [
    'impersonating' => '正在模拟',
    'leave'         => '结束模拟',
];
