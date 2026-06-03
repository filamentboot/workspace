<?php

use FilamentAdmin\Settings\GeneralSettings;

it('通用配置可以读写站点名称', function () {
    $settings             = app(GeneralSettings::class);
    $settings->site_name  = '测试站点';
    $settings->save();

    // 重新从容器解析，确认持久化
    app()->forgetInstance(GeneralSettings::class);
    $fresh = app(GeneralSettings::class);

    expect($fresh->site_name)->toBe('测试站点');
});
