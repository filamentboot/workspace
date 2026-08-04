<?php

namespace Filamentboot\FilamentbootSite\Cms\Themes;

use Filamentboot\FilamentbootSite\Cms\Models\SitePage;

/**
 * 主题切换预检查（#28）
 *
 * 算出「切到目标主题后，哪些已发布页面会掉内容」。
 *
 * 只查已发布页面：草稿与归档页现在不对外，等它们发布时目标主题可能又变了，
 * 那时再拦更准。检查结果里会点明这一点，免得看到「0 个受影响」就以为草稿也安全。
 *
 * 与 BlockRenderer 的运行时兜底是两回事：那个是缺视图就跳过并记 warning——
 * 事后降级，页面上悄悄少一块，没人收到通知。本类是切换之前先算一遍。
 */
class ThemeSwitchCheck
{
    /**
     * 目标主题不支持、但已发布页面正在用的东西
     *
     * @return array{
     *     templates: list<string>,
     *     blocks: list<string>,
     *     pages: list<array{id: int, title: string, slug: string, reasons: list<string>}>,
     * }
     */
    public function inspect(string $targetTheme): array
    {
        $manifest = ThemeManifest::for($targetTheme);

        $supportedTemplates = $manifest->templates();
        $supportedBlocks    = $manifest->blocks();

        $templates = [];
        $blocks    = [];
        $pages     = [];

        foreach (SitePage::published()->get(['id', 'title_zh', 'slug', 'template', 'blocks']) as $page) {
            $reasons = [];

            $template = (string) ($page->template ?? '');

            // 空 template 与 default 等价，控制器都落到 pages.show
            if ($template !== '' && $template !== 'default' && ! in_array($template, $supportedTemplates, true)) {
                $templates[] = $template;
                $reasons[]   = "版式「{$template}」";
            }

            foreach ($this->blockKeysOf($page) as $key) {
                if (in_array($key, $supportedBlocks, true)) {
                    continue;
                }

                $blocks[]  = $key;
                $reasons[] = "区块「{$key}」";
            }

            if ($reasons === []) {
                continue;
            }

            $pages[] = [
                'id'      => (int) $page->getKey(),
                'title'   => (string) $page->title_zh,
                'slug'    => (string) $page->slug,
                'reasons' => array_values(array_unique($reasons)),
            ];
        }

        return [
            'templates' => array_values(array_unique($templates)),
            'blocks'    => array_values(array_unique($blocks)),
            'pages'     => $pages,
        ];
    }

    /**
     * 目标主题是否完全支持当前已发布内容
     */
    public function passes(string $targetTheme): bool
    {
        return $this->inspect($targetTheme)['pages'] === [];
    }

    /**
     * 页面用到的区块 key 列表
     *
     * @return list<string>
     */
    protected function blockKeysOf(SitePage $page): array
    {
        $keys = [];

        foreach (is_array($page->blocks) ? $page->blocks : [] as $block) {
            if (is_array($block) && is_string($block['type'] ?? null) && $block['type'] !== '') {
                $keys[] = $block['type'];
            }
        }

        return array_values(array_unique($keys));
    }
}
