<?php

namespace FilamentAdmin\Settings;

use Spatie\LaravelSettings\Settings;

/**
 * 上传配置 Settings 类
 *
 * 存储文件上传相关配置，包括大小限制、允许类型、默认磁盘。
 */
class UploadSettings extends Settings
{
    /** 最大上传文件大小（KB） */
    public int $max_file_size = 10240;

    /** 允许上传的文件扩展名（逗号分隔） */
    public string $allowed_mimes = 'jpg,jpeg,png,gif,webp,pdf,doc,docx,xls,xlsx,zip';

    /** 默认存储磁盘 */
    public string $default_disk = 'public';

    /**
     * Settings 分组名
     */
    public static function group(): string
    {
        return 'upload';
    }
}
