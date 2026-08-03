<?php

namespace Filamentboot\FilamentbootSite\Support;

/**
 * 前台富文本净化
 *
 * 后台各内容资源用的是 Filament RichEditor，默认工具栏能产出 h2/h3、居中、
 * 引用、代码块、删除线、上下标、表格（见 Filament\Forms\Components\RichEditor
 * ::getDefaultToolbarButtons()）。而 mews/purifier 的 default 画像白名单只有
 * div,b,strong,i,em,u,a,ul,ol,li,p,br,span,img —— 用它过滤前台正文的实测结果：
 *
 * - h2 / h3 与相邻标题被合并进同一个 <p>，标题和正文再也分不出来
 * - s / sub / sup 直接消失，「x<sup>2</sup>」变成「x2」，语义反了
 * - blockquote、pre/code、hr 全部丢失
 * - table 整张塌成一串散段落，表头与单元格并列成兄弟 <p>
 *
 * 编辑在后台排好版、预览也正常，一到前台结构没了，且不报任何错。
 * 所以这里挂一份包自带的画像，白名单与编辑器工具栏对齐。
 *
 * 为什么把画像写成数组直传，而不是往 config('purifier.settings') 里注册一段：
 * purifier.php 是宿主的配置文件，下游装本包时里面可能只有 mews 的 stock 内容，
 * clean($html, '某个不存在的画像名') 会静默退化成 HTMLPurifier 的裸默认策略
 * （仍然防 XSS，但白名单完全不是这里声明的这套）。数组直传在任何安装形态下
 * 结果都一致，实测已验证：去掉宿主的 custom_definition / custom_elements
 * 定制段后，输出与完整配置下逐字节相同（唯一差异见 target 的说明）。
 *
 * 安全性与 default 画像等价，实测同一份含 script / iframe / onclick /
 * javascript: / img onerror 的样本，两个画像的输出完全一致——放宽的只有
 * 展示性标签，XSS 防护由 HTMLPurifier 的默认策略保障，不受白名单放宽影响。
 */
final class RichText
{
    /**
     * 净化并返回可直接 {!! !!} 输出的 HTML
     */
    public static function purify(?string $html): string
    {
        $html = (string) $html;

        if ($html === '') {
            return '';
        }

        return (string) app('purifier')->clean($html, self::purifierProfile());
    }

    /**
     * 解析实际生效的 purifier 画像
     *
     * 宿主想接管过滤策略时，把 config('filamentboot-site.purifier_profile')
     * 设成自己在 config/purifier.php 里定义的画像名即可，包内白名单让位。
     *
     * @return array<string, mixed>|string
     */
    protected static function purifierProfile(): array|string
    {
        $named = config('filamentboot-site.purifier_profile');

        if (is_string($named) && $named !== '') {
            return $named;
        }

        return self::defaultProfile();
    }

    /**
     * 包内自带画像
     *
     * Doctype 取 HTML 4.01 Transitional 而非 Strict：u / s 这类展示性标签
     * 只在 Transitional 的 Legacy 模块里有定义，Strict 下会被判为未知元素剥掉。
     *
     * AutoParagraph 关掉：TipTap 交出来的已经是规整的块级结构，再自动分段会把
     * 已有段落切碎。RemoveEmpty 同样关掉——编辑器里的空行是作者故意留的间距，
     * 存成 <p></p>，删掉等于替作者改版式。
     *
     * a 的 target 留在白名单里，但它能不能活下来取决于宿主 config/purifier.php
     * 是否保留了 mews stock 的 custom_attributes（['a','target','Enum#...']）。
     * 缺了就退化成同标签打开，属可接受降级，因此不为它单独去动宿主配置。
     * target 存在时 HTMLPurifier 会自动补 rel="noreferrer noopener"。
     *
     * @return array<string, mixed>
     */
    protected static function defaultProfile(): array
    {
        return [
            'HTML.Doctype' => 'HTML 4.01 Transitional',
            'HTML.Allowed' => 'h1,h2,h3,h4,h5,h6,'
                .'p[style],div[style],br,hr,'
                .'strong,b,em,i,u,s,del,ins,sub,sup,code,'
                .'a[href|title|target|rel],'
                .'ul,ol,li,blockquote,pre,'
                .'table,thead,tbody,tfoot,tr,th[colspan|rowspan|style],td[colspan|rowspan|style],'
                .'span[style],img[src|alt|width|height]',
            'CSS.AllowedProperties' => 'text-align,color,background-color,'
                .'font-weight,font-style,text-decoration,width,height',
            'AutoFormat.AutoParagraph' => false,
            'AutoFormat.RemoveEmpty'   => false,
        ];
    }
}
