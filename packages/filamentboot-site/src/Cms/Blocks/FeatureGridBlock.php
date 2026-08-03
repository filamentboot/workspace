<?php

namespace Filamentboot\FilamentbootSite\Cms\Blocks;

use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;

/**
 * 特性网格区块（#12）
 *
 * 多个「图标 + 标题 + 描述」的卡片，用于服务优势、能力清单。
 */
class FeatureGridBlock extends AbstractBlock
{
    public function key(): string
    {
        return 'feature-grid';
    }

    public function label(): string
    {
        return '特性网格';
    }

    /**
     * @return array<int, mixed>
     */
    public function schema(): array
    {
        return [
            TextInput::make('title')
                ->label('区块标题')
                ->maxLength(120),
            Select::make('columns')
                ->label('每行列数')
                ->options([2 => '2 列', 3 => '3 列', 4 => '4 列'])
                ->default(3)
                ->native(false)
                ->required(),
            Repeater::make('items')
                ->label('特性条目')
                ->minItems(1)
                ->maxItems(12)
                ->defaultItems(3)
                ->schema([
                    TextInput::make('icon')
                        ->label('图标')
                        ->maxLength(60)
                        ->helperText('Heroicons 名称，如 heroicon-o-shield-check'),
                    TextInput::make('title')
                        ->label('标题')
                        ->required()
                        ->maxLength(60),
                    Textarea::make('description')
                        ->label('描述')
                        ->rows(2)
                        ->maxLength(300),
                ]),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title'               => ['nullable', 'string', 'max:120'],
            'columns'             => ['required', 'integer', 'in:2,3,4'],
            'items'               => ['required', 'array', 'min:1', 'max:12'],
            'items.*.icon'        => ['nullable', 'string', 'max:60'],
            'items.*.title'       => ['required', 'string', 'max:60'],
            'items.*.description' => ['nullable', 'string', 'max:300'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function defaults(): array
    {
        return [
            'title'   => '',
            'columns' => 3,
            'items'   => [],
        ];
    }
}
