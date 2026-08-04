<?php

namespace Filamentboot\FilamentbootSite\Cms\Rendering;

use Filamentboot\FilamentbootSite\Support\RichText;

/**
 * 区块 payload 保存侧净化（#13）
 *
 * 由 SitePageResource 的 mutateFormDataBeforeCreate/Save 调用，
 * 对 rich-content 区块的 content 跑一遍富文本白名单。
 *
 * 渲染侧（区块视图里的 RichText::purify()）已经过一遍，这里再过是有意重复：
 * 只在渲染侧过，数据库里就一直躺着未净化的 HTML，任何绕过前台视图的出口
 * （API、导出、日后的搜索索引）都会把它原样带出去。保存侧过一遍等于让
 * 存量数据随每次编辑逐步被治理。
 *
 * content 是页面里唯一允许 HTML 的字段（见 RichContentBlock 的类注释），
 * 因此这里只处理它——其余字段在视图侧一律 {{ }} 转义，不需要也不应该
 * 在保存时改写作者输入。
 */
class BlockSanitizer
{
    /**
     * 净化整页区块 payload
     *
     * 输入形状不保证（可能来自历史版本或 seeder），非预期结构原样放行：
     * 净化器的职责是过滤 HTML，不是校验结构——结构问题由区块 rules() 管，
     * 在这里"顺手修正"会静默丢掉作者数据。
     *
     * @param  array<int, mixed>|null  $blocks
     * @return array<int, mixed>
     */
    public function sanitize(?array $blocks): array
    {
        $sanitized = [];

        foreach ($blocks ?? [] as $key => $block) {
            $sanitized[$key] = is_array($block) ? $this->sanitizeOne($block) : $block;
        }

        return $sanitized;
    }

    /**
     * 净化单个区块条目
     *
     * @param  array<string, mixed>  $block
     * @return array<string, mixed>
     */
    protected function sanitizeOne(array $block): array
    {
        if (($block['type'] ?? null) !== 'rich-content') {
            return $block;
        }

        if (! is_array($block['data'] ?? null) || ! isset($block['data']['content'])) {
            return $block;
        }

        $block['data']['content'] = RichText::purify((string) $block['data']['content']);

        return $block;
    }
}
