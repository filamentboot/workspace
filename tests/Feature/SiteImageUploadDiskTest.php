<?php

/**
 * 图片上传磁盘测试桩（SiteImageUploadDiskTest）
 *
 * Wave 0 安全网测试，由 Plan 10-04 落地转绿。
 * 覆盖 SITE-04 跨切：案例封面图上传使用 UploadSettings.default_disk 配置的磁盘。
 *
 * @group site
 */

/**
 * 目标可观测信号：SiteCaseResource 中 SpatieMediaLibraryFileUpload 的 getDiskName()
 * 返回值等于 app(UploadSettings::class)->default_disk
 * （由 Plan 10-04 集成 UploadSettings 磁盘配置到上传组件后落地转绿，SITE-04 跨切）
 */
it('案例封面图上传使用 UploadSettings 默认磁盘', function () {
    $this->markTestIncomplete(
        '待 10-04 落地（SITE-04 跨切）：SpatieMediaLibraryFileUpload getDiskName() 应等于 app(UploadSettings::class)->default_disk'
    );
});
