<?php

namespace Filamentboot\FilamentbootMarkdownEditor;

use Filament\Support\Assets\Js;
use Filament\Support\Facades\FilamentAsset;
use Illuminate\Support\ServiceProvider;

/**
 * Markdown 编辑器服务提供者
 *
 * 职责：
 * 1. 注册 EasyMDE 分屏预览增强 JS 资产（通过 FilamentAsset::register）
 * 2. 注册代码高亮 CSS/JS 资产（highlight.js，EasyMDE codeSyntaxHighlighting 支持）
 *
 * 注意：本 ServiceProvider 轻量，不执行数据库查询，
 * 所有配置通过 MarkdownEditorField 链式 API 完成（D-09-11/D-09-12）。
 */
class MarkdownEditorServiceProvider extends ServiceProvider
{
    /**
     * 注册 Filament 资产（分屏预览增强 JS + 代码高亮）
     *
     * 分屏预览：通过自定义 JS 在 EasyMDE 初始化后调用 toggleSideBySide()。
     * 代码高亮：EasyMDE 内置 highlight.js 支持，通过 codeSyntaxHighlighting: true 选项开启。
     * 资产文件位于 resources/dist/，生产环境应预先构建。
     *
     * Pitfall 1：EasyMDE side-by-side/preview 按钮未在 Filament 5 toolbarButtons API 暴露
     *            （Issue #12185 已关为 not planned），须通过编程方式开启。
     */
    public function boot(): void
    {
        // FilamentAsset 注册扩展点：
        // 当 resources/dist/markdown-editor-enhancements.js 存在时，取消注释下方代码：
        //
        // FilamentAsset::register([
        //     Js::make(
        //         'markdown-editor-enhancements',
        //         __DIR__ . '/../resources/dist/markdown-editor-enhancements.js'
        //     ),
        // ], package: 'filamentboot/filamentboot-markdown-editor');
        //
        // JS 文件应在 EasyMDE init 完成后执行 EasyMDE.toggleSideBySide(editor)，
        // 并启用 codeSyntaxHighlighting: true 通过 EasyMDE renderingConfig 配置高亮。
    }
}
