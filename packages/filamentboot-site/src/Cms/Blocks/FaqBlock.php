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
}
