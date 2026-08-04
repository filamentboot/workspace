<?php

use Filamentboot\FilamentbootSite\Modules\Corporate\Cases\Filament\SiteCaseResource;
use Filamentboot\Settings\UploadSettings;

/**
 * 图片上传磁盘测试（SiteImageUploadDiskTest）
 *
 * 覆盖场景：
 * - SiteCaseResource 的 SpatieMediaLibraryFileUpload 字段使用 UploadSettings.default_disk（SITE-04 跨切）
 * - resolveDefaultDisk() 静态方法降级到 'public'（防 settings 表未迁移崩溃）
 *
 * @group site
 */

/**
 * 案例封面图上传使用 UploadSettings 默认磁盘（SITE-04 跨切）
 *
 * 验证 SiteCaseResource::resolveDefaultDisk() 返回值与 UploadSettings.default_disk 一致。
 */
it('案例封面图上传使用 UploadSettings 默认磁盘', function () {
    // 通过反射访问 protected static resolveDefaultDisk 方法
    $reflection = new ReflectionMethod(SiteCaseResource::class, 'resolveDefaultDisk');
    $reflection->setAccessible(true);
    $actualDisk = $reflection->invoke(null); // static 方法传 null

    // 获取 UploadSettings 实例并读取 default_disk
    try {
        $uploadSettings = app(UploadSettings::class);
        $expectedDisk   = $uploadSettings->default_disk;
    } catch (Throwable) {
        // settings 表未迁移时降级 'public'
        $expectedDisk = 'public';
    }

    // 断言两者一致（SITE-04 跨切：site 包图片走 Phase 8 默认磁盘配置）
    expect($actualDisk)->toBe($expectedDisk);
});

/**
 * resolveDefaultDisk 在 settings 表未迁移时安全降级到 'public'
 *
 * 验证降级防护机制（T-10-03-03，防止 UploadSettings 解析失败崩溃）。
 */
it('resolveDefaultDisk 降级到 public 磁盘当设置不可用时', function () {
    // Mock UploadSettings 解析抛出异常（模拟 settings 表未迁移）
    app()->bind(UploadSettings::class, fn () => throw new RuntimeException('settings table not found'));

    $reflection = new ReflectionMethod(SiteCaseResource::class, 'resolveDefaultDisk');
    $reflection->setAccessible(true);
    $disk = $reflection->invoke(null);

    // 降级应为 'public'
    expect($disk)->toBe('public');

    // 恢复绑定（清理副作用）
    app()->forgetInstance(UploadSettings::class);
    app()->offsetUnset(UploadSettings::class);
});
