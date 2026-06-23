<?php

namespace Filamentboot\FilamentbootWangEditor\Forms\Components;

use Closure;
use Filament\Forms\Components\Field;
use Filamentboot\Settings\UploadSettings;

/**
 * wangEditor 自定义富文本编辑器字段
 *
 * 实现 Filament 5 custom field 模式（extends Field），客户可用本字段替换默认 Tiptap RichEditor
 * （D-09-03/D-09-04）。启用 wangEditor 插件后，将 WangEditorField::make('content') 置于表单中即可。
 *
 * 图片上传磁盘优先级（D-09-07/D-09-08/D-09-12）：
 * 1. 组件级 ->disk('oss') 配置（最高优先）
 * 2. UploadSettings.default_disk（读数据库配置）
 * 3. config('filesystems.default')（框架默认，兜底）
 * 4. 'local' 时一律回退 'public'（避免私有路径）
 *
 * 图片上传由 WangEditorUploadController 接收，经 UploadValidator 三重安全校验落盘（D-09-09）。
 * CSRF 防护：前端 customUpload 附加 X-CSRF-TOKEN header（T-09-07）。
 */
class WangEditorField extends Field
{
    /**
     * 字段渲染视图（filamentboot-wang-editor::components.wang-editor）
     */
    protected string $view = 'filamentboot-wang-editor::components.wang-editor';

    /**
     * 组件级自定义磁盘，null 时读全局配置（D-09-12）
     */
    protected string|Closure|null $disk = null;

    /**
     * 配置上传磁盘（D-09-12）
     *
     * 组件级磁盘配置优先于 UploadSettings 全局配置。
     *
     * @param  string|Closure|null  $disk  磁盘名称，如 'oss'、'cos'、'public'
     */
    public function disk(string|Closure|null $disk): static
    {
        $this->disk = $disk;

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
    public function getDisk(): string
    {
        // D-09-12：组件级磁盘优先
        $customDisk = $this->evaluate($this->disk);

        if (filled($customDisk)) {
            return $customDisk;
        }

        // D-09-08：读 UploadSettings，回退 filesystems.default
        try {
            $settings = app(UploadSettings::class);
            $disk     = $settings->default_disk;
        } catch (\Throwable) {
            $disk = config('filesystems.default', 'public');
        }

        // 禁止 local 磁盘直出（避免私有路径问题）
        return ($disk === 'local') ? 'public' : $disk;
    }
}
