<?php

namespace Filamentboot\FilamentbootRichEditor\Support;

use Mews\Purifier\Purifier;

/**
 * 富文本 HTML XSS 过滤服务
 *
 * 封装 mews/purifier（HTMLPurifier 的 Laravel 适配），
 * 提供保存前 HTML 净化能力（D-09-10）。
 *
 * 使用时机：
 * - 富文本 HTML 保存前（Filament mutateFormDataBeforeSave 中调用）
 * - 禁止直接 {!! $html !!} 不过滤输出（D-09-10）
 *
 * 配置白名单（Pitfall 4 修复）：
 * 在 config/purifier.php 的 richeditor 配置中允许 span[style]/p[style] 等，
 * 防止 Tiptap 生成的对齐/颜色/字号样式被 HTMLPurifier 默认规则过滤。
 * 示例：$data['content'] = app(RichEditorPurifier::class)->clean($data['content'], 'richeditor');
 *
 * 安全说明（T-09-01）：
 * HTMLPurifier 采用白名单 HTML 解析树，而非 regex 替换，
 * 能防御嵌套、属性注入、编码绕过等边界情况。
 */
class RichEditorPurifier
{
    /**
     * @param  Purifier  $purifier  mews/purifier 注入实例
     */
    public function __construct(private readonly Purifier $purifier) {}

    /**
     * 过滤 HTML 中的 XSS 危险内容
     *
     * @param  string       $html    原始 HTML 内容（来自 Tiptap 编辑器）
     * @param  string|null  $config  purifier 配置 key，null 时使用默认配置；
     *                               可传 'richeditor' 使用允许 style 属性的白名单配置
     * @return string 净化后的 HTML（<script> 等危险标签已移除）
     */
    public function clean(string $html, ?string $config = null): string
    {
        if ($config !== null) {
            return $this->purifier->clean($html, $config);
        }

        return $this->purifier->clean($html);
    }
}
