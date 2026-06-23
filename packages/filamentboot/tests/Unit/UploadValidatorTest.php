<?php

namespace Filamentboot\Tests\Unit;

use Filamentboot\Settings\UploadSettings;
use Filamentboot\Support\UploadValidator;
use Illuminate\Http\UploadedFile;
use PHPUnit\Framework\TestCase;

/**
 * UploadValidator 上传安全校验测试
 *
 * 验证上传文件的扩展名黑名单、文件大小限制、服务端 MIME 类型校验。
 * 全部用例使用纯 PHPUnit TestCase（不需要 Laravel 容器），
 * 通过第二参数直接传入 UploadSettings 实例，避免引导完整应用。
 */
class UploadValidatorTest extends TestCase
{
    /**
     * 构造基础 UploadSettings，允许 jpg/jpeg/png，大小 10240KB
     *
     * 使用反射绕过 Spatie Settings 的 final __construct 对容器的依赖，
     * 使得纯单元测试不需要 Laravel 容器即可运行。
     */
    private function makeSettings(
        int $maxFileSizeKb = 10240,
        string $allowedMimes = 'jpg,jpeg,png,gif,webp,pdf,doc,docx,xls,xlsx,zip'
    ): UploadSettings {
        // 用反射跳过 final __construct，避免触发 spatie/laravel-settings 对容器的依赖
        $reflection = new \ReflectionClass(UploadSettings::class);
        /** @var UploadSettings $settings */
        $settings                = $reflection->newInstanceWithoutConstructor();
        $settings->max_file_size = $maxFileSizeKb;
        $settings->allowed_mimes = $allowedMimes;
        $settings->default_disk  = 'public';

        return $settings;
    }

    /**
     * 验证：客户端原始扩展名为 .php 的文件被拒绝，异常消息含 ".php"
     */
    public function test_rejects_dangerous_extension(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/\.php/i');

        // 构造一个客户端文件名含 .php 扩展名的 UploadedFile（测试模式不验证实际路径）
        $file      = UploadedFile::fake()->create('malicious.php', 10);
        $validator = new UploadValidator;
        $validator->validate($file, $this->makeSettings());
    }

    /**
     * 验证：每个危险扩展名均被拦截（危险列表循环测试）
     */
    public function test_rejects_each_dangerous_extension(): void
    {
        $dangerousExtensions = UploadValidator::DANGEROUS_EXTENSIONS;

        foreach ($dangerousExtensions as $ext) {
            $thrown = false;

            try {
                $file      = UploadedFile::fake()->create("malicious.{$ext}", 10);
                $validator = new UploadValidator;
                $validator->validate($file, $this->makeSettings());
            } catch (\RuntimeException $e) {
                $thrown = true;
            }

            $this->assertTrue($thrown, "扩展名 .{$ext} 应当被拦截，但未抛出异常");
        }
    }

    /**
     * 验证：文件大小超过 max_file_size（KB）限制时抛出异常，消息含"大小"
     */
    public function test_rejects_oversize_file(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/大小/u');

        // 限制 1KB，文件内容 2KB
        $settings = $this->makeSettings(1);
        // 创建真实临时文件（内容 2048 字节）
        $tmpPath = tempnam(sys_get_temp_dir(), 'uv_test_');
        file_put_contents($tmpPath, str_repeat('x', 2048));

        $file = new UploadedFile(
            $tmpPath,
            'large.jpg',
            'image/jpeg',
            null,
            true // 测试模式
        );

        try {
            $validator = new UploadValidator;
            $validator->validate($file, $settings);
        } finally {
            @unlink($tmpPath);
        }
    }

    /**
     * 验证：文件真实 MIME（finfo 检测）与允许列表不符时抛出异常
     *
     * 构造一个：客户端声明 .jpg 扩展名，但实际内容为纯文本（text/plain）的临时文件。
     */
    public function test_rejects_mime_mismatch(): void
    {
        $this->expectException(\RuntimeException::class);

        // 创建内容为纯文本的临时文件，但以 .jpg 扩展名提交
        $tmpPath = tempnam(sys_get_temp_dir(), 'uv_test_');
        file_put_contents($tmpPath, 'This is plain text content, not a JPEG image.');

        $file = new UploadedFile(
            $tmpPath,
            'fake.jpg',        // 客户端声明 jpg
            'image/jpeg',      // 客户端声明 MIME（不可信）
            null,
            true               // 测试模式
        );

        try {
            $validator = new UploadValidator;
            // allowed_mimes = jpg,jpeg,png —— jpg 映射到 image/jpeg，但文件真实 MIME 是 text/plain
            $validator->validate($file, $this->makeSettings(10240, 'jpg,jpeg,png'));
        } finally {
            @unlink($tmpPath);
        }
    }

    /**
     * 验证：合法文件（扩展名安全、大小未超限、真实 MIME 匹配）不抛出异常
     */
    public function test_passes_valid_file(): void
    {
        // 创建真实的 JPEG 文件内容（SOI marker + minimal bytes）
        $jpegContent = "\xFF\xD8\xFF\xE0".str_repeat("\x00", 100);
        $tmpPath     = tempnam(sys_get_temp_dir(), 'uv_test_');
        file_put_contents($tmpPath, $jpegContent);

        $file = new UploadedFile(
            $tmpPath,
            'valid.jpg',
            'image/jpeg',
            null,
            true
        );

        try {
            $validator = new UploadValidator;
            // 期望不抛出异常
            $validator->validate($file, $this->makeSettings(10240, 'jpg,jpeg,png'));
            $this->assertTrue(true, '合法文件应通过校验');
        } finally {
            @unlink($tmpPath);
        }
    }
}
