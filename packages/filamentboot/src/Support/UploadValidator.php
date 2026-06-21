<?php

namespace Filamentboot\Support;

use Filamentboot\Settings\UploadSettings;
use Illuminate\Http\UploadedFile;

/**
 * 上传文件安全校验服务
 *
 * 联动 UploadSettings 提供三重校验：
 * 1. 危险扩展名黑名单（防止可执行脚本上传）
 * 2. 文件大小限制（防止 DoS 攻击）
 * 3. 服务端 finfo 真实 MIME 校验（不信任 HTTP Content-Type 头，防止 MIME 欺骗）
 */
class UploadValidator
{
    /**
     * 危险文件扩展名黑名单（一律小写）
     *
     * 覆盖 D-08-09 要求的危险扩展名列表，以及常见可执行脚本类型。
     *
     * @var list<string>
     */
    public const DANGEROUS_EXTENSIONS = [
        'php', 'php3', 'php4', 'php5', 'phtml', 'phar',
        'exe', 'sh', 'bat', 'cmd', 'ps1',
        'py', 'rb', 'pl', 'cgi',
    ];

    /**
     * 扩展名到允许 MIME 类型的映射表
     *
     * 覆盖 UploadSettings 默认 allowed_mimes 中的常见扩展名。
     * 对未知扩展名采用保守策略（空数组），即默认拒绝。
     *
     * @var array<string, list<string>>
     */
    private const EXTENSION_MIME_MAP = [
        'jpg'  => ['image/jpeg'],
        'jpeg' => ['image/jpeg'],
        'png'  => ['image/png'],
        'gif'  => ['image/gif'],
        'webp' => ['image/webp'],
        'pdf'  => ['application/pdf'],
        'zip'  => ['application/zip', 'application/x-zip-compressed'],
        'doc'  => ['application/msword'],
        'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
        'xls'  => ['application/vnd.ms-excel'],
        'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
        'txt'  => ['text/plain'],
        'csv'  => ['text/csv', 'text/plain'],
        'mp4'  => ['video/mp4'],
        'mp3'  => ['audio/mpeg'],
    ];

    /**
     * 校验上传文件是否安全
     *
     * 按顺序执行：大小校验 → 扩展名黑名单 → 服务端 MIME 校验。
     * 任意一项不通过均抛出 RuntimeException。
     *
     * @param  UploadedFile  $file      待校验的上传文件
     * @param  UploadSettings|null  $settings  上传配置（null 时从容器解析）
     *
     * @throws \RuntimeException 文件不安全时
     */
    public function validate(UploadedFile $file, ?UploadSettings $settings = null): void
    {
        $settings ??= app(UploadSettings::class);

        // 1. 大小校验
        $this->validateSize($file, $settings);

        // 2. 扩展名黑名单校验
        $this->validateExtension($file);

        // 3. 服务端真实 MIME 校验（不信任客户端 Content-Type 头）
        $this->validateMimeType($file, $settings);
    }

    /**
     * 校验文件大小是否超过限制
     *
     * @throws \RuntimeException 超过限制时
     */
    private function validateSize(UploadedFile $file, UploadSettings $settings): void
    {
        $limitBytes = $settings->max_file_size * 1024;

        if ($file->getSize() > $limitBytes) {
            throw new \RuntimeException(
                "文件大小超过限制 {$settings->max_file_size}KB，当前大小：" .
                round($file->getSize() / 1024, 2) . 'KB'
            );
        }
    }

    /**
     * 校验文件扩展名是否在危险列表中
     *
     * @throws \RuntimeException 扩展名在黑名单中时
     */
    private function validateExtension(UploadedFile $file): void
    {
        $ext = strtolower($file->getClientOriginalExtension());

        if (in_array($ext, self::DANGEROUS_EXTENSIONS, true)) {
            throw new \RuntimeException("不允许上传 .{$ext} 类型文件");
        }
    }

    /**
     * 用 finfo 检测文件真实 MIME，校验是否在 allowed_mimes 允许范围内
     *
     * 不信任 $file->getClientMimeType() 或 HTTP Content-Type 头，
     * 通过 FILEINFO_MIME_TYPE 直接读取文件字节特征。
     *
     * @throws \RuntimeException 真实 MIME 不在允许列表中时
     */
    private function validateMimeType(UploadedFile $file, UploadSettings $settings): void
    {
        $realPath = $file->getRealPath();

        if ($realPath === false || ! file_exists($realPath)) {
            // 无法获取真实路径时，保守拒绝
            throw new \RuntimeException('无法读取上传文件，MIME 校验失败');
        }

        // 使用 finfo 检测真实 MIME（不信任客户端声明）
        $finfo    = new \finfo(FILEINFO_MIME_TYPE);
        $realMime = $finfo->file($realPath);

        if ($realMime === false) {
            throw new \RuntimeException('无法检测文件 MIME 类型');
        }

        // 将 allowed_mimes 扩展名列表转为允许 MIME 集合
        $allowedMimes = $this->extensionsToMimes(
            array_map('trim', explode(',', $settings->allowed_mimes))
        );

        if (! in_array($realMime, $allowedMimes, true)) {
            throw new \RuntimeException(
                "文件真实类型 {$realMime} 不在允许的 MIME 类型列表中"
            );
        }
    }

    /**
     * 将扩展名数组转换为允许的 MIME 类型数组
     *
     * 未在映射表中的扩展名采用保守策略：映射为空集合（即拒绝该类型）。
     *
     * @param  list<string>  $extensions  扩展名列表（小写）
     * @return list<string> 允许的 MIME 类型列表
     */
    private function extensionsToMimes(array $extensions): array
    {
        $mimes = [];

        foreach ($extensions as $ext) {
            $ext = strtolower(trim($ext));

            if (isset(self::EXTENSION_MIME_MAP[$ext])) {
                foreach (self::EXTENSION_MIME_MAP[$ext] as $mime) {
                    $mimes[] = $mime;
                }
            }
            // 未知扩展名：保守策略，不添加任何 MIME（防止未知类型被放行）
        }

        return array_unique($mimes);
    }
}
