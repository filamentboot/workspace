<?php

namespace LaravelStack\FilamentAdminRichEditor\Forms;

use Closure;
use Filament\Forms\Components\RichEditor;
use FilamentAdmin\Settings\UploadSettings;

/**
 * 增强版富文本编辑器字段
 *
 * 继承 Filament 5 内置 Tiptap RichEditor，扩展：
 * - 图片上传磁盘动态读取 UploadSettings.default_disk，回退 config('filesystems.default')（D-09-08）
 * - 禁止 'local' 磁盘直出，回退 'public'（D-09-08）
 * - 组件级磁盘覆盖 ->disk('oss')（D-09-12）
 * - 文件大小限制联动 UploadSettings.max_file_size
 *
 * 保存前 XSS 过滤由调用方在 mutateFormDataBeforeSave 中调用：
 * $data['content'] = app(\LaravelStack\FilamentAdminRichEditor\Support\RichEditorPurifier::class)->clean($data['content']);
 * 禁止直接 {!! $html !!} 不过滤输出（D-09-10）
 */
class RichEditorField extends RichEditor
{
    /**
     * 组件级自定义磁盘，null 时读全局配置（D-09-12）
     */
    protected string|Closure|null $customDisk = null;

    /**
     * 配置上传磁盘（D-09-12）
     *
     * 组件级磁盘配置优先于 UploadSettings 全局配置。
     *
     * @param  string|Closure|null  $disk  磁盘名称，如 'oss'、'cos'、'public'
     * @return static
     */
    public function disk(string|Closure|null $disk): static
    {
        $this->customDisk = $disk;

        return $this;
    }

    /**
     * 解析最终生效的上传磁盘名称
     *
     * 优先级（D-09-07/D-09-08/D-09-12）：
     * 1. 组件级 disk() 配置（最高优先）
     * 2. UploadSettings.default_disk（读数据库配置）
     * 3. config('filesystems.default')（框架默认，兜底）
     * 4. 'local' 时一律回退 'public'（避免私有路径）
     *
     * @return string 实际生效的磁盘名称
     */
    public function resolveDisk(): string
    {
        // D-09-12：组件级磁盘优先
        $customDisk = $this->evaluate($this->customDisk);
        if (filled($customDisk)) {
            return $customDisk;
        }

        // D-09-08：读 UploadSettings，回退 filesystems.default
        try {
            $settings = app(UploadSettings::class);
            $disk = $settings->default_disk;
        } catch (\Throwable) {
            $disk = config('filesystems.default', 'public');
        }

        // 禁止 local 磁盘直出（避免私有路径问题）
        return ($disk === 'local') ? 'public' : $disk;
    }

    /**
     * 字段初始化钩子
     *
     * 配置动态磁盘和文件大小限制，联动 UploadSettings（D-09-07/D-09-08）。
     */
    protected function setUp(): void
    {
        parent::setUp();

        // D-09-08：动态磁盘闭包，委托 resolveDisk() 解析
        $this->fileAttachmentsDisk(function (): string {
            return $this->resolveDisk();
        });

        // 联动 UploadSettings 文件大小限制（KB 单位）
        $this->fileAttachmentsMaxSize(function (): int {
            try {
                return app(UploadSettings::class)->max_file_size;
            } catch (\Throwable) {
                return 12288; // 默认 12MB（单位 KB）
            }
        });
    }
}
