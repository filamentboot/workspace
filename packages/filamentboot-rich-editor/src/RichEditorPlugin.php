<?php

namespace Filamentboot\FilamentbootRichEditor;

use Filament\Contracts\Plugin;
use Filament\Panel;

/**
 * 富文本编辑器 Filament 插件
 *
 * 通过 ->plugins([RichEditorPlugin::make()]) 注册到 Filament Panel。
 * 本插件基于 Filament 5 内置 Tiptap RichEditor 进行增强：
 * - 图片上传动态磁盘联动 UploadSettings（D-09-07/D-09-08）
 * - 保存前 XSS 过滤（D-09-10）
 * - 工具栏链式配置（D-09-11）
 * - 组件级磁盘指定（D-09-12）
 */
class RichEditorPlugin implements Plugin
{
    /**
     * 创建插件实例（通过 IoC 容器）
     */
    public static function make(): static
    {
        return app(static::class);
    }

    /**
     * 插件唯一标识符，与 extra.filamentboot.slug 保持一致
     */
    public function getId(): string
    {
        return 'filamentboot-rich-editor';
    }

    /**
     * 向 Panel 注册资源
     *
     * 富文本编辑器插件无独立设置页面，工具栏通过链式 API 配置（D-09-11）。
     * 若后续需要新增设置页，在此 $panel->pages([...])。
     *
     * @param  Panel  $panel  当前 Filament 面板实例
     */
    public function register(Panel $panel): void
    {
        // 编辑器插件无需注册额外 Pages/Widgets，
        // 工具栏通过 RichEditorField 链式 API 配置（D-09-11）。
    }

    /**
     * 插件启动钩子
     *
     * FilamentAsset 资产注册由 RichEditorServiceProvider::boot() 完成，
     * 此处无需重复操作（wangEditor 资产由 09-02 实现）。
     *
     * @param  Panel  $panel  当前 Filament 面板实例
     */
    public function boot(Panel $panel): void
    {
        // 资产注册（wangEditor CDN + Alpine component）由
        // RichEditorServiceProvider::boot() 在 09-02 中执行。
    }
}
