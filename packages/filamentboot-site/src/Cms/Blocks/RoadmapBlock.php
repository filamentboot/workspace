<?php

namespace Filamentboot\FilamentbootSite\Cms\Blocks;

use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;

/**
 * 路线图区块（五期批次 4d）
 *
 * 带状态徽标的功能列表：每条只挂「已有 / 开发中 / 计划中」三档之一，
 * **刻意不提供日期字段**——四期已拍板 Roadmap 只分三档不写日期，写了日期
 * 就变成对外的交付承诺，开源项目普遍不这么干。
 *
 * 内容与 `docs/cms/竞品调研/功能清单.md` 同步：官网上多写一条，那份清单里
 * 就得有对应的一条，两边不许分叉——这是内容纪律，不是本区块的技术约束。
 */
class RoadmapBlock extends AbstractBlock
{
    public function key(): string
    {
        return 'roadmap';
    }

    public function label(): string
    {
        return '路线图';
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
            Repeater::make('items')
                ->label('功能条目')
                ->minItems(1)
                ->maxItems(60)
                ->defaultItems(1)
                ->schema([
                    Select::make('status')
                        ->label('状态')
                        ->options([
                            'available'   => '已有',
                            'in_progress' => '开发中',
                            'planned'     => '计划中',
                        ])
                        ->default('planned')
                        ->native(false)
                        ->required(),
                    TextInput::make('title')
                        ->label('标题')
                        ->required()
                        ->maxLength(80),
                    Textarea::make('description')
                        ->label('说明')
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
            'items'               => ['required', 'array', 'min:1', 'max:60'],
            'items.*.status'      => ['required', 'string', 'in:available,in_progress,planned'],
            'items.*.title'       => ['required', 'string', 'max:80'],
            'items.*.description' => ['nullable', 'string', 'max:300'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function defaults(): array
    {
        return [
            'title' => '',
            'items' => [],
        ];
    }
}
