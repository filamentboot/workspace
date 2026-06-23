<?php

namespace Filamentboot\FilamentbootRichEditor\Tests\Unit;

use Filamentboot\FilamentbootRichEditor\Forms\RichEditorField;
use Filamentboot\FilamentbootRichEditor\RichEditorServiceProvider;
use Filamentboot\Settings\UploadSettings;
use Illuminate\Foundation\Application;
use Orchestra\Testbench\TestCase;

/**
 * RichEditorField 单元测试
 *
 * 验证磁盘解析四场景（D-09-07/D-09-08/D-09-12）：
 * 1. 组件级 disk() 覆盖优先
 * 2. 读取 UploadSettings.default_disk
 * 3. UploadSettings.default_disk='local' 时回退 'public'
 * 4. UploadSettings 不可解析时回退 config('filesystems.default')
 */
class RichEditorFieldTest extends TestCase
{
    /**
     * 注册包服务提供者
     *
     * @param  Application  $app
     * @return list<class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [RichEditorServiceProvider::class];
    }

    /**
     * 场景1：组件级 disk('oss') 调用后，resolveDisk 应返回 'oss'
     *
     * D-09-12：组件级磁盘配置优先于全局配置。
     */
    public function test_disk_override(): void
    {
        // 绑定 UploadSettings stub，默认磁盘为 'public'
        $this->app->bind(UploadSettings::class, function () {
            $stub               = $this->createStub(UploadSettings::class);
            $stub->default_disk = 'public';

            return $stub;
        });

        $field = RichEditorField::make('content');
        $field->disk('oss');

        self::assertSame('oss', $field->resolveDisk());
    }

    /**
     * 场景2：未调用 disk() 时，读取 UploadSettings.default_disk='cos'
     *
     * D-09-07/D-09-08：图片上传路由到当前生效的文件系统盘。
     */
    public function test_disk_reads_upload_settings(): void
    {
        // 绑定 UploadSettings stub，默认磁盘为 'cos'
        $this->app->bind(UploadSettings::class, function () {
            $stub               = $this->createStub(UploadSettings::class);
            $stub->default_disk = 'cos';

            return $stub;
        });

        $field = RichEditorField::make('content');

        self::assertSame('cos', $field->resolveDisk());
    }

    /**
     * 场景3：UploadSettings.default_disk='local' 时，回退 'public'
     *
     * D-09-08：不允许 local 直出，避免私有路径问题。
     */
    public function test_disk_local_falls_back_public(): void
    {
        // 绑定 UploadSettings stub，默认磁盘为 'local'
        $this->app->bind(UploadSettings::class, function () {
            $stub               = $this->createStub(UploadSettings::class);
            $stub->default_disk = 'local';

            return $stub;
        });

        $field = RichEditorField::make('content');

        self::assertSame('public', $field->resolveDisk());
    }

    /**
     * 场景4：UploadSettings 不可解析时，回退 config('filesystems.default')
     *
     * 防止 settings 表未迁移时崩溃（同 OssServiceProvider T-08-02 防护模式）。
     */
    public function test_disk_falls_back_config_when_settings_missing(): void
    {
        // 模拟 UploadSettings 无法解析（抛出异常）
        $this->app->bind(UploadSettings::class, function () {
            throw new \RuntimeException('settings 表未迁移');
        });

        // 设置 filesystems.default 为 'public'
        config(['filesystems.default' => 'public']);

        $field = RichEditorField::make('content');

        self::assertSame('public', $field->resolveDisk());
    }
}
