<?php

namespace Filamentboot\FilamentbootSite\Cms\Blocks;

use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;

/**
 * 常见问题区块（#12）
 *
 * 问答列表。B1 的 JSON-LD FAQPage 结构化数据将以此区块的数据为来源，
 * 因此答案存纯文本而非 HTML——结构化数据里带标签会被搜索引擎判为无效。
 */
class FaqBlock extends AbstractBlock
{
    public function key(): string
    {
        return 'faq';
    }

    public function label(): string
    {
        return '常见问题';
    }

    /**
     * @return array<int, mixed>
     */
    public function schema(): array
    {
        return [
            TextInput::make('title')
                ->label('区块标题')
                ->maxLength(120)
                ->default('常见问题'),
            Repeater::make('items')
                ->label('问答条目')
                ->minItems(1)
                ->maxItems(30)
                ->defaultItems(3)
                ->schema([
                    TextInput::make('question')
                        ->label('问题')
                        ->required()
                        ->maxLength(200),
                    Textarea::make('answer')
                        ->label('答案')
                        ->rows(3)
                        ->required()
                        ->maxLength(1000)
                        ->helperText('纯文本，不要粘贴 HTML：该内容会同时用于生成结构化数据'),
                ]),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title'            => ['nullable', 'string', 'max:120'],
            'items'            => ['required', 'array', 'min:1', 'max:30'],
            'items.*.question' => ['required', 'string', 'max:200'],
            'items.*.answer'   => ['required', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function defaults(): array
    {
        return [
            'title' => '常见问题',
            'items' => [],
        ];
    }

    /**
     * 转 schema.org FAQPage 节点（七期批次 1 从 BlockRenderer::faqNode() 下沉）
     *
     * 问答不完整（缺问或缺答）的条目跳过；一条都不剩则整个节点不输出——
     * 空 mainEntity 的 FAQPage 会被 Search Console 报为无效结构化数据。
     * 答案本就存纯文本（见类注释），结构化数据里带 HTML 标签会被搜索引擎
     * 判为无效，因此不做任何转义外的处理。
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>|null
     */
    public function structuredData(array $data): ?array
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
