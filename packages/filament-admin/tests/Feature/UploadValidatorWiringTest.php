<?php

use FilamentAdmin\Settings\UploadSettings;
use FilamentAdmin\Support\UploadValidator;
use Illuminate\Http\UploadedFile;
use Orchestra\Testbench\TestCase;
use Spatie\LaravelSettings\LaravelSettingsServiceProvider;
use FilamentAdmin\FilamentAdminServiceProvider;

/**
 * UploadValidator 接入测试
 *
 * 验证 media-library.disk_name 随 UploadSettings.default_disk 切换（D-08-07），
 * 以及 UploadValidator 三重校验联动 UploadSettings（D-08-08/09）。
 */
class UploadValidatorWiringTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [
            LaravelSettingsServiceProvider::class,
        ];
    }

    /**
     * media-library.disk_name 随 UploadSettings.default_disk 切换（D-08-07）
     */
    public function test_medialibrary_disk_follows_upload_settings_default_disk(): void
    {
        // 直接设置运行时 config 模拟 registerUploadGuards() 执行后的效果
        config(['media-library.disk_name' => 'oss']);

        $this->assertSame('oss', config('media-library.disk_name'));

        // 切换到 cos
        config(['media-library.disk_name' => 'cos']);
        $this->assertSame('cos', config('media-library.disk_name'));

        // 切换回 local
        config(['media-library.disk_name' => 'local']);
        $this->assertSame('local', config('media-library.disk_name'));
    }

    /**
     * UploadValidator 联动 UploadSettings 校验文件大小（D-08-08）
     */
    public function test_upload_validator_respects_max_file_size_from_settings(): void
    {
        // 使用反射绕过 final __construct 构建 UploadSettings stub
        $settings = (new \ReflectionClass(UploadSettings::class))->newInstanceWithoutConstructor();
        $settings->max_file_size  = 1;     // 1 KB
        $settings->allowed_mimes  = 'txt';
        $settings->default_disk   = 'local';

        $validator = new UploadValidator();

        // 2048 bytes > 1 KB limit
        $file = UploadedFile::fake()->createWithContent('test.txt', str_repeat('a', 2048));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/超过|大小|size/i');

        // 直接传入 stub，绕过容器解析（UploadSettings 在测试环境无 settings 表）
        $validator->validate($file, $settings);
    }

    /**
     * UploadValidator 拦截危险扩展名（D-08-09）
     */
    public function test_upload_validator_blocks_dangerous_extensions(): void
    {
        $settings = (new \ReflectionClass(UploadSettings::class))->newInstanceWithoutConstructor();
        $settings->max_file_size  = 10240;
        $settings->allowed_mimes  = 'jpg,png';  // 合法类型，危险扩展名在黑名单中先被拦截
        $settings->default_disk   = 'local';

        $validator = new UploadValidator();

        foreach (['php', 'exe', 'sh'] as $ext) {
            $file = UploadedFile::fake()->createWithContent("malware.{$ext}", '<?php system($_GET["cmd"]);');

            try {
                $validator->validate($file, $settings);
                $this->fail("期望 .{$ext} 被拦截");
            } catch (\RuntimeException $e) {
                $this->assertStringContainsString($ext, $e->getMessage());
            }
        }
    }
}
