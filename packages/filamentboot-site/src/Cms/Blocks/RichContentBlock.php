<?php

namespace Filamentboot\FilamentbootSite\Cms\Blocks;

use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\TextInput;

/**
 * 富文本区块（#12）
 *
 * ⚠️ 该区块的 content 是页面里唯一允许 HTML 的字段。
 * 渲染必须经 mews/purifier 白名单过滤（沿用 pages/show.blade.php 的做法），
 * 保存侧同样过一遍——两侧都过是为了让存量数据也被治理，
 * 只在渲染侧过则数据库里一直躺着未净化的内容。
 */
class RichContentBlock extends AbstractBlock
{
    public function key(): string
    {
        return 'rich-content';
    }

    public function label(): string
    {
        return '富文本内容';
    }

    /**
     * @return array<int, mixed>
     */
    public function schema(): array
    {
        return [
            TextInput::make('title')
                ->label('小标题')
                ->maxLength(120)
                ->helperText('留空则不显示标题'),
            RichEditor::make('content')
                ->label('正文')
                ->required()
                ->helperText('保存与渲染两侧都会经白名单过滤，脚本标签会被剥离'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title'   => ['nullable', 'string', 'max:120'],
            'content' => ['required', 'string'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function defaults(): array
    {
        return [
            'title'   => '',
            'content' => '',
        ];
    }
}
