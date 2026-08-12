<?php

namespace Filamentboot\FilamentbootSite\Cms\Rendering;

use Filamentboot\FilamentbootSite\Cms\Blocks\BlockRegistry;

/**
 * 区块 payload 保存侧净化（#13）
 *
 * 由 SitePageResource 的 mutateFormDataBeforeCreate/Save 调用，
 * 对每个区块调它自己的 BlockContract::sanitize()（七期批次 1 起——此前
 * 硬编码只认 rich-content，新增一个含 HTML 字段的区块得回来改这个类；
 * 现在只需要改自己的区块类，默认实现（AbstractBlock）原样放行）。
 *
 * 渲染侧（区块视图里的 RichText::purify()）已经过一遍，这里再过是有意重复：
 * 只在渲染侧过，数据库里就一直躺着未净化的 HTML，任何绕过前台视图的出口
 * （API、导出、日后的搜索索引）都会把它原样带出去。保存侧过一遍等于让
 * 存量数据随每次编辑逐步被治理。
 */
class BlockSanitizer
{
    public function __construct(protected BlockRegistry $registry) {}

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
     * 未注册的区块 type 原样放行——净化不是白名单校验（那是 BlockRenderer
     * 渲染时的事），遇到不认识的 key 在这里拦下来只会让保存流程凭空报错。
     *
     * @param  array<string, mixed>  $block
     * @return array<string, mixed>
     */
    protected function sanitizeOne(array $block): array
    {
        $type = $block['type'] ?? null;

        if (! is_string($type)) {
            return $block;
        }

        $definition = $this->registry->get($type);

        if ($definition === null || ! is_array($block['data'] ?? null)) {
            return $block;
        }

        $block['data'] = $definition->sanitize($block['data']);

        return $block;
    }
}
