<?php

namespace Filamentboot\FilamentbootMarkdownEditor\Forms;

use Filament\Forms\Components\MarkdownEditor;
use Filamentboot\Settings\UploadSettings;

/**
 * 增强版 Markdown 编辑器字段
 *
 * 在 Filament 5 内置 EasyMDE MarkdownEditor 基础上新增：
 * - 动态图片上传磁盘（联动 UploadSettings.default_disk，D-09-07/D-09-08）
 * - 分屏预览声明（D-09-05，通过 getExtraAlpineAttributes 注入 Alpine 指令）
 *
 * 安全约定：
 * - Pitfall 5：Markdown 图片 visibility 必须为 public，禁止调用 fileAttachmentsVisibility('private')。
 *   Filament 5 源码第 61-64 行：fileAttachmentsVisibility('private') 会抛 LogicException。
 * - Pitfall 1：EasyMDE side-by-side/preview 按钮在 Filament 5 toolbar API 未暴露
 *   （Issue #12185 关为 not planned），须通过 getExtraAlpineAttributes + Alpine init 指令开启。
 */
class MarkdownEditorField extends MarkdownEditor
{
    /** 分屏预览开关，默认开启（D-09-05） */
    protected bool $sideBySide = true;

    /**
     * 初始化字段：配置动态磁盘
     *
     * 注意：绝不调用 fileAttachmentsVisibility('private')（Pitfall 5）。
     */
    protected function setUp(): void
    {
        parent::setUp();

        // D-09-08：动态磁盘，不硬编码 local；优先读 UploadSettings，回退 config()
        $this->fileAttachmentsDisk(function (): string {
            return $this->resolveDisk();
        });
    }

    /**
     * 解析图片上传磁盘
     *
     * 优先级：
     * 1. UploadSettings::default_disk（Phase 8 云存储配置）
     * 2. config('filesystems.default', 'public')（兜底）
     *
     * local 磁盘强制映射为 public（Markdown 图片必须可通过 URL 公开访问，Pitfall 5）。
     *
     * @return string 有效的 Flysystem 磁盘名
     */
    public function resolveDisk(): string
    {
        try {
            $disk = app(UploadSettings::class)->default_disk;
        } catch (\Throwable) {
            // settings 表未迁移或 UploadSettings 不可用时，读 filesystems.default
            $disk = config('filesystems.default', 'public');
        }

        // Markdown 图片不支持 private disk（Pitfall 5）：local → public
        return ($disk === 'local') ? 'public' : $disk;
    }

    /**
     * 开启或关闭分屏预览（D-09-05）
     *
     * Pitfall 1：不能通过 toolbarButtons(['side-by-side']) 实现，
     * 必须在 Alpine init 完成后通过 x-on:init.camel 编程调用。
     *
     * @param  bool  $condition  是否开启分屏预览
     * @return static 支持链式调用
     */
    public function withSideBySide(bool $condition = true): static
    {
        $this->sideBySide = $condition;

        return $this;
    }

    /**
     * 获取额外的 Alpine 属性，注入分屏预览触发指令（D-09-05）
     *
     * EasyMDE 初始化完成后，Alpine 的 x-on:init.camel 事件触发，
     * 调用插件包注册的 enableSideBySide() 方法开启分屏预览。
     *
     * @return array<string, string> Alpine 属性键值对
     */
    public function getExtraAlpineAttributes(): array
    {
        $parent = parent::getExtraAlpineAttributes();

        if (! $this->sideBySide) {
            return $parent;
        }

        // 注入分屏预览触发指令（在 EasyMDE init 完成后调用 toggleSideBySide）
        return array_merge($parent, [
            'x-on:init.camel' => 'enableSideBySide()',
        ]);
    }
}
