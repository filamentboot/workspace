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

it('通用配置可以保存并读取 logo_url', function () {
    $settings            = app(GeneralSettings::class);
    $settings->logo_url  = 'https://example.com/logo.png';
    $settings->save();

    // 重新从容器解析，确认持久化往返一致
    app()->forgetInstance(GeneralSettings::class);
    $fresh = app(GeneralSettings::class);

    expect($fresh->logo_url)->toBe('https://example.com/logo.png');
});

it('通用配置可以保存并读取 contact_email', function () {
    $settings                  = app(GeneralSettings::class);
    $settings->contact_email   = 'contact@example.com';
    $settings->save();

    // 重新从容器解析，确认持久化往返一致
    app()->forgetInstance(GeneralSettings::class);
    $fresh = app(GeneralSettings::class);

    expect($fresh->contact_email)->toBe('contact@example.com');
});
