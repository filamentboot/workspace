<?php

namespace Filamentboot\FilamentbootMarkdownEditor\Tests\Unit;

use Filamentboot\FilamentbootMarkdownEditor\Support\MarkdownRenderer;
use Orchestra\Testbench\TestCase;

/**
 * MarkdownRenderer 单元测试
 *
 * 验证 D-09-06/D-09-10 核心行为：
 * - Markdown 原文经 CommonMark（GFM）转换为 HTML
 * - HTML 经 HTMLPurifier 过滤 XSS（展示时过滤，禁止 {!! !!} 直出）
 * - GFM 语法（表格等）正确渲染
 */
class MarkdownRendererTest extends TestCase
{
    /**
     * 测试 Markdown 标题转换为 HTML h1 标签
     *
     * D-09-06：Markdown 在详情页用 CommonMark 转 HTML
     */
    public function test_render_converts_heading(): void
    {
        $renderer = new MarkdownRenderer();

        $html = $renderer->render('# Title');

        self::assertStringContainsString('<h1>Title</h1>', $html);
    }

    /**
     * 测试 script 标签被 XSS 过滤（html_input=escape + HTMLPurifier 双重过滤）
     *
     * D-09-10：禁止 {!! $html !!} 直出，展示时必须过滤 XSS
     * T-09-03：Markdown 转 HTML 渲染威胁缓解
     */
    public function test_render_filters_xss_script_tags(): void
    {
        $renderer = new MarkdownRenderer();

        $html = $renderer->render('<script>alert(1)</script>');

        self::assertStringNotContainsString('<script>', $html);
    }

    /**
     * 测试 GFM 表格语法渲染为 HTML table 标签
     *
     * D-09-05：增强 EasyMDE，支持 GFM（GitHub Flavored Markdown）
     */
    public function test_render_preserves_gfm_table(): void
    {
        $renderer = new MarkdownRenderer();

        $markdown = "| 列A | 列B |\n|-----|-----|\n| 1   | 2   |";
        $html = $renderer->render($markdown);

        self::assertStringContainsString('<table>', $html);
    }
}
