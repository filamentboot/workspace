<?php

namespace Filamentboot\FilamentbootMarkdownEditor\Tests\Unit;

use Filament\Forms\Components\MarkdownEditor;
use Filamentboot\FilamentbootMarkdownEditor\Forms\MarkdownEditorField;
use Filamentboot\FilamentbootMarkdownEditor\MarkdownEditorServiceProvider;
use Filamentboot\Settings\UploadSettings;
use Illuminate\Foundation\Application;
use Orchestra\Testbench\TestCase;

/**
 * MarkdownEditorField 单元测试
 *
 * 验证 EDITOR-02 核心行为：
 * - 继承 Filament 内置 MarkdownEditor（保留所有内置功能）
 * - 动态磁盘读取 UploadSettings.default_disk（D-09-07/D-09-08）
 * - 强制 public 可见性（Pitfall 5 防护，禁止 private disk）
 */
class MarkdownEditorFieldTest extends TestCase
{
    /**
     * 注册包服务提供者
     *
     * @param  Application  $app
     * @return list<class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [MarkdownEditorServiceProvider::class];
    }

    /**
     * 验证 MarkdownEditorField 继承自 Filament 内置 MarkdownEditor
     */
    public function test_extends_markdown_editor(): void
    {
        $field = MarkdownEditorField::make('content');

        self::assertInstanceOf(MarkdownEditor::class, $field);
    }

    /**
     * 验证磁盘读取 UploadSettings.default_disk（非 local 磁盘直接返回）
     *
     * D-09-07：图片上传必须路由到当前生效的文件系统盘
     */
    public function test_disk_reads_upload_settings(): void
    {
        $this->app->bind(UploadSettings::class, function () {
            $stub               = $this->createStub(UploadSettings::class);
            $stub->default_disk = 'cos';

            return $stub;
        });

        $field = MarkdownEditorField::make('content');
        $disk  = $field->resolveDisk();

        self::assertSame('cos', $disk);
    }

    /**
     * 验证 default_disk='local' 时回退到 'public'（Pitfall 5：Markdown 不支持 private disk）
     *
     * Filament MarkdownEditor::fileAttachmentsVisibility('private') 会抛 LogicException，
     * 因此 local 磁盘（private）必须映射为 public。
     */
    public function test_disk_local_falls_back_to_public(): void
    {
        $this->app->bind(UploadSettings::class, function () {
            $stub               = $this->createStub(UploadSettings::class);
            $stub->default_disk = 'local';

            return $stub;
        });

        $field = MarkdownEditorField::make('content');
        $disk  = $field->resolveDisk();

        self::assertSame('public', $disk);
    }

    /**
     * 验证 UploadSettings 不可解析时回退到 config('filesystems.default')
     *
     * 容器中无 UploadSettings 绑定时（如独立使用场景），读取 filesystems.default 配置。
     */
    public function test_disk_falls_back_to_config_when_settings_unavailable(): void
    {
        config(['filesystems.default' => 'public']);

        $this->app->bind(UploadSettings::class, function () {
            throw new \RuntimeException('Settings table not found');
        });

        $field = MarkdownEditorField::make('content');
        $disk  = $field->resolveDisk();

        // config('filesystems.default') = 'public'（非 local），直接返回
        self::assertSame('public', $disk);
    }
}
