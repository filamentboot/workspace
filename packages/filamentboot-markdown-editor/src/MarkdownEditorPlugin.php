<?php

namespace Filamentboot\FilamentbootMarkdownEditor;

use Filament\Contracts\Plugin;
use Filament\Panel;

/**
 * Markdown 编辑器 Filament 插件
 *
 * 通过 ->plugins([MarkdownEditorPlugin::make()]) 注册到 Filament Panel。
 * 本插件不注册独立 Settings Page（工具栏通过链式 API 配置，磁盘读 UploadSettings）。
 * 资产（分屏预览增强 JS、代码高亮 CSS）注册由 MarkdownEditorServiceProvider::boot() 完成。
 */
class MarkdownEditorPlugin implements Plugin
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
        return 'filamentboot-markdown-editor';
    }

    /**
     * 向 Panel 注册资源（本插件无独立 Page/Widget）
     *
     * @param  Panel  $panel  当前 Filament 面板实例
     */
    public function register(Panel $panel): void
    {
        // Markdown 编辑器插件无需注册额外 Pages/Widgets。
        // 若后续新增 Markdown 设置页，在此 $panel->pages([...])。
    }

    /**
     * 插件启动钩子（资产注册已在 MarkdownEditorServiceProvider::boot 完成）
     *
     * @param  Panel  $panel  当前 Filament 面板实例
     */
    public function boot(Panel $panel): void
    {
        // 分屏预览增强 JS 和代码高亮资产已在 MarkdownEditorServiceProvider::boot() 注册，
        // 此处无需重复操作。
    }
}
