<?php

namespace LaravelStack\FilamentAdminMarkdownEditor\Support;

use HTMLPurifier;
use HTMLPurifier_Config;
use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\GithubFlavoredMarkdownExtension;
use League\CommonMark\MarkdownConverter;

/**
 * Markdown 渲染器
 *
 * 将 Markdown 原文转换为 XSS 安全的 HTML，供 Blade 视图展示。
 *
 * 设计决策（D-09-06/D-09-10）：
 * - 原始 Markdown 原文保留在数据库（不在保存前过滤），因为过滤会破坏 Markdown 语法本身
 * - 渲染时经 league/commonmark（GFM）转 HTML，再经 HTMLPurifier 过滤 XSS
 * - 禁止 {!! $html !!} 直出未过滤内容（T-09-03 威胁缓解）
 *
 * 安全措施（T-09-03）：
 * - html_input=escape：转义 Markdown 中嵌入的原始 HTML（双重保险）
 * - allow_unsafe_links=false：过滤 javascript: 等危险链接
 * - GithubFlavoredMarkdownExtension：支持 GFM 语法（表格、任务列表、删除线等）
 * - HTMLPurifier 最终 XSS 白名单过滤（Markdown 专用白名单，允许 h1-h6/table 等）
 *
 * 注：直接使用 ezyang/htmlpurifier（mews/purifier 底层库），避免 mews/purifier 默认
 * 白名单过于保守且 finalize 后无法运行时扩展的限制（Pitfall 4）。
 */
class MarkdownRenderer
{
    /**
     * Markdown 渲染用 HTMLPurifier 白名单
     *
     * 包含 Markdown → HTML 产出的所有标准节点：
     * - 块级：h1-h6, p, pre, blockquote, hr, ul, ol, li, table 系列
     * - 行内：strong, b, em, i, u, s, del, code, a, img, span
     *
     * 不含 script/style/iframe 等危险元素（XSS 缓解）。
     */
    private const HTML_ALLOWED = 'h1,h2,h3,h4,h5,h6,p,br,'
        . 'strong,b,em,i,u,s,del,'
        . 'a[href|title],ul,ol,li,'
        . 'code,pre,blockquote,hr,'
        . 'table,thead,tbody,tr,th,td,'
        . 'span,div,img[src|alt|width|height]';

    /**
     * 将 Markdown 文本转换为 XSS 安全的 HTML
     *
     * 渲染流程：
     * 1. CommonMark 环境配置（html_input=escape，GFM 扩展）
     * 2. league/commonmark 将 Markdown 转换为 HTML
     * 3. HTMLPurifier 对 HTML 做最终 XSS 过滤（Markdown 专用白名单）
     *
     * @param  string  $markdown  原始 Markdown 文本
     * @return string  XSS 过滤后的安全 HTML
     */
    public function render(string $markdown): string
    {
        $environment = new Environment([
            'html_input'         => 'escape',    // 转义 raw HTML（双重保险，防止 Markdown 中嵌入恶意 HTML）
            'allow_unsafe_links' => false,        // 过滤 javascript:、vbscript: 等危险链接
        ]);

        // CommonMark 核心渲染器（段落、标题、列表、行内元素等基础节点）
        $environment->addExtension(new CommonMarkCoreExtension());
        // GFM 扩展：支持表格、任务列表、删除线、自动链接
        $environment->addExtension(new GithubFlavoredMarkdownExtension());

        $converter = new MarkdownConverter($environment);
        $html = $converter->convert($markdown)->getContent();

        // HTMLPurifier 最终 XSS 过滤（Markdown 专用白名单）
        // 直接实例化以避免 mews/purifier finalize 后无法运行时扩展白名单的限制
        $config = HTMLPurifier_Config::createDefault();
        $config->set('HTML.Allowed', self::HTML_ALLOWED);
        $config->set('AutoFormat.AutoParagraph', false);
        $config->set('AutoFormat.RemoveEmpty', false);

        return (new HTMLPurifier($config))->purify($html);
    }
}
