<?php

namespace Filamentboot\FilamentbootSite\Cms\Rendering;

use Filamentboot\FilamentbootSite\Cms\Blocks\BlockRegistry;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\View;
use Illuminate\Support\HtmlString;

/**
 * 区块前台渲染器（#13）
 *
 * 把 site_pages.blocks 里的 [{type, data}, ...] 逐条渲染成 HTML 拼接输出。
 *
 * 为什么是 PHP 渲染器而不是 Blade 分发器：视图命名空间的主题优先级由
 * SiteServiceProvider::registerThemeViews() 控制，走 view() 天然吃到那套解析，
 * 因此同一份 payload 在不同主题下自动落到各自那份视图；而「跳过未知 key
 * 并记日志」这条降级逻辑写在 PHP 里可单测，写在 Blade 里不能。
 *
 * 两处降级都不抛异常（BlockRegistry::get() 的注释已定这个契约）：
 * 一个失效区块不能把整页打成 500——页面上少一段内容是可接受的损失，
 * 整页白屏不是。两处都记 warning，运维能从日志发现有内容没显示出来。
 */
class BlockRenderer
{
    public function __construct(protected BlockRegistry $registry) {}

    /**
     * 渲染整页区块
     *
     * @param  array<int, mixed>|null  $blocks  site_pages.blocks 原值
     */
    public function render(?array $blocks): HtmlString
    {
        $html = '';

        foreach ($this->normalize($blocks) as $index => $entry) {
            $html .= $this->renderOne($entry['type'], $entry['data'], $index);
        }

        return new HtmlString($html);
    }

    /**
     * 从区块中提取结构化数据节点（B1 的 FAQPage 部分，由 #13 承接）
     *
     * 目前只有 faq 区块产出节点。FaqBlock 的答案本就存纯文本（见该类注释），
     * 结构化数据里带 HTML 标签会被搜索引擎判为无效，因此不做任何转义外的处理。
     *
     * 返回列表而非单节点：一页可以放多个 FAQ 区块，也为日后其它区块贡献
     * 自己的节点类型留口子。调用方并入 $seoData['jsonLd']（已支持节点列表）。
     *
     * @param  array<int, mixed>|null  $blocks
     * @return list<array<string, mixed>>
     */
    public function structuredData(?array $blocks): array
    {
        $nodes = [];

        foreach ($this->normalize($blocks) as $entry) {
            if ($entry['type'] !== 'faq') {
                continue;
            }

            $node = $this->faqNode($entry['data']);

            if ($node !== null) {
                $nodes[] = $node;
            }
        }

        return $nodes;
    }

    /**
     * 渲染单个区块，任一降级条件命中则返回空串
     *
     * $index 是区块在本页的序号，视图用它拼 aria 关联所需的稳定 DOM id。
     * 不用 uniqid()：同一份内容每次请求会产出不同 HTML，既让断言无从下手，
     * 也让 #29 的整页缓存命中率无从验证。
     *
     * @param  array<string, mixed>  $data
     */
    protected function renderOne(string $type, array $data, int $index): string
    {
        $block = $this->registry->get($type);

        if ($block === null) {
            Log::warning('官网页面引用了未注册的区块，已跳过渲染。', [
                'block_type' => $type,
                'registered' => $this->registry->keys(),
            ]);

            return '';
        }

        $view = $block->view();

        if (! View::exists($view)) {
            Log::warning('官网区块视图缺失，已跳过渲染。', [
                'block_type' => $type,
                'view'       => $view,
            ]);

            return '';
        }

        return View::make($view, [
            'data'  => $block->withDefaults($data),
            'block' => $block,
            'index' => $index,
        ])->render();
    }

    /**
     * 把原始 blocks 值整理成 [{type: string, data: array}] 列表
     *
     * 库里的 JSON 可能来自 seeder、tinker 或历史版本，形状不保证。
     * 缺 type 或 type 非字符串的条目直接丢弃：没有 key 就无从查注册表，
     * 记日志也说不出是哪个区块，属于纯脏数据。
     *
     * @param  array<int, mixed>|null  $blocks
     * @return list<array{type: string, data: array<string, mixed>}>
     */
    protected function normalize(?array $blocks): array
    {
        $entries = [];

        foreach ($blocks ?? [] as $block) {
            if (! is_array($block)) {
                continue;
            }

            $type = $block['type'] ?? null;

            if (! is_string($type) || $type === '') {
                continue;
            }

            $data = $block['data'] ?? [];

            $entries[] = [
                'type' => $type,
                'data' => is_array($data) ? $data : [],
            ];
        }

        return $entries;
    }

    /**
     * 单个 faq 区块转 schema.org FAQPage 节点
     *
     * 问答不完整（缺问或缺答）的条目跳过；一条都不剩则整个节点不输出——
     * 空 mainEntity 的 FAQPage 会被 Search Console 报为无效结构化数据。
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>|null
     */
    protected function faqNode(array $data): ?array
    {
        $items = $data['items'] ?? [];

        if (! is_array($items)) {
            return null;
        }

        $entities = [];

        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }

            $question = trim((string) ($item['question'] ?? ''));
            $answer   = trim((string) ($item['answer'] ?? ''));

            if ($question === '' || $answer === '') {
                continue;
            }

            $entities[] = [
                '@type'          => 'Question',
                'name'           => $question,
                'acceptedAnswer' => [
                    '@type' => 'Answer',
                    'text'  => $answer,
                ],
            ];
        }

        if ($entities === []) {
            return null;
        }

        return [
            '@context'   => 'https://schema.org',
            '@type'      => 'FAQPage',
            'mainEntity' => $entities,
        ];
    }
}
