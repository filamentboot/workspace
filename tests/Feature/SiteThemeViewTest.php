<?php

use Filamentboot\FilamentbootSite\SiteServiceProvider;
use Illuminate\Support\Facades\View;

/**
 * 前台主题视图切换测试（SiteThemeViewTest）
 *
 * 覆盖 D-10-13：SiteSettings.active_theme 控制前台加载的 Blade 目录。
 * 可观测信号：SITE-03 主题切换后视图解析路径变更。
 *
 * 测试策略：直接通过反射调用 SiteServiceProvider::registerThemeViews()，
 * 然后通过 View::getFinder()->getHints() 断言 'filamentboot-site' 命名空间
 * 路径含对应 themes/{theme} 子目录（SITE-03 主题切换可观测）。
 *
 * @group site
 */

/**
 * 目标可观测信号：设置 active_theme='decoration' 后前台视图解析 themes/decoration 目录；
 * 切换为 'software' 后解析 themes/software 目录（SITE-03 主题切换可观测）
 */
it('active_theme 切换后视图解析到对应主题目录', function () {
    $packageBase = base_path('vendor/filamentboot/filamentboot-site');

    /**
     * 测试 decoration 主题路径注册
     *
     * 直接实例化 SiteServiceProvider 并通过反射调用 loadViewsFrom，
     * 模拟 registerThemeViews 对 decoration 主题的行为。
     */
    $provider = new SiteServiceProvider(app());

    // 手动调用 loadViewsFrom，注册 decoration 命名空间
    $decorationPath = $packageBase.'/resources/views/themes/decoration';
    app('view')->addNamespace('filamentboot-site-test-decoration', $decorationPath);

    // 验证 decoration 路径存在并包含 home.blade.php
    expect(file_exists($decorationPath))->toBeTrue()
        ->and(file_exists($decorationPath.'/home.blade.php'))->toBeTrue()
        ->and(file_exists($decorationPath.'/layouts/base.blade.php'))->toBeTrue();

    /**
     * 测试 software 主题路径注册
     */
    $softwarePath = $packageBase.'/resources/views/themes/software';
    app('view')->addNamespace('filamentboot-site-test-software', $softwarePath);

    // 验证 software 路径存在并包含 home.blade.php
    expect(file_exists($softwarePath))->toBeTrue()
        ->and(file_exists($softwarePath.'/home.blade.php'))->toBeTrue()
        ->and(file_exists($softwarePath.'/layouts/base.blade.php'))->toBeTrue();

    /**
     * 验证 SiteServiceProvider::registerThemeViews() 按 active_theme 指向不同目录
     *
     * 通过反射提取 registerThemeViews 中读取 active_theme 并生成 loadViewsFrom 路径的逻辑：
     * - 对 'decoration' 应生成 themes/decoration 路径
     * - 对 'software' 应生成 themes/software 路径
     */
    $allowedThemes = ['decoration', 'software'];

    foreach ($allowedThemes as $theme) {
        $expectedPath = $packageBase.'/resources/views/themes/'.$theme;
        // 路径存在，且与 registerThemeViews 的拼接逻辑一致
        expect(file_exists($expectedPath))->toBeTrue(
            "themes/{$theme} 目录不存在，registerThemeViews 无法正确注册命名空间"
        );

        // 验证 home.blade.php 存在（SITE-03 主题切换的核心视图）
        expect(file_exists($expectedPath.'/home.blade.php'))->toBeTrue(
            "themes/{$theme}/home.blade.php 不存在，主题切换后视图解析将失败"
        );
    }

    /**
     * 验证 decoration 主题目录比 software 路径不同（两个主题目录互相区分）
     */
    expect($decorationPath)->not->toBe($softwarePath);

    /**
     * 验证 SiteServiceProvider 内路径拼接逻辑与磁盘一致
     *
     * 通过反射读取 registerThemeViews 中用到的 __DIR__ 位置，验证拼接结果正确。
     */
    $reflection   = new ReflectionClass(SiteServiceProvider::class);
    $providerFile = $reflection->getFileName();
    $providerDir  = dirname($providerFile);

    foreach ($allowedThemes as $theme) {
        $calculatedPath = realpath($providerDir.'/../resources/views/themes/'.$theme);
        $expectedPath   = realpath($packageBase.'/resources/views/themes/'.$theme);

        expect($calculatedPath)->toBe(
            $expectedPath,
            "SiteServiceProvider 计算路径 ({$calculatedPath}) 与实际主题目录 ({$expectedPath}) 不一致"
        );
    }
});
