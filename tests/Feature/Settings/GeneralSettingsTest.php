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

// Wave 0 占位：待 Plan 03 实现 GeneralSettings 新字段后转为真实断言（FINAL-03）

it('通用配置可以保存并读取 logo_url', function () {
    $this->markTestIncomplete('Wave 0 占位：待 Plan 03 实现 — 断言 GeneralSettings::logo_url 可保存并从数据库正确读取（FINAL-03）');
});

it('通用配置可以保存并读取 contact_email', function () {
    $this->markTestIncomplete('Wave 0 占位：待 Plan 03 实现 — 断言 GeneralSettings::contact_email 可保存并从数据库正确读取（FINAL-03）');
});
