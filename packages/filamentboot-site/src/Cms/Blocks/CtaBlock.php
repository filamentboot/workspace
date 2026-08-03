<?php

namespace Filamentboot\FilamentbootSite\Cms\Blocks;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;

/**
 * 行动号召区块（#12）
 *
 * 一句话主张 + 一个按钮，用于页面中段与结尾的转化点。
 *
 * button_url 留空时前台应改为打开询盘面板（对应 A1 的 source 埋点），
 * 这样这个区块本身就是一个可归因的转化入口。
 */
class CtaBlock extends AbstractBlock
{
    public function key(): string
    {
        return 'cta';
    }

    public function label(): string
    {
        return '行动号召';
    }

    /**
     * @return array<int, mixed>
     */
    public function schema(): array
    {
        return [
            TextInput::make('title')
                ->label('主张')
                ->required()
                ->maxLength(120),
            Textarea::make('description')
                ->label('补充说明')
                ->rows(2)
                ->maxLength(300),
            TextInput::make('button_label')
                ->label('按钮文字')
                ->required()
                ->maxLength(30),
            TextInput::make('button_url')
                ->label('按钮链接')
                ->maxLength(500)
                ->helperText('留空则点击后打开询盘面板，来源标识记为 page-cta'),
            Select::make('style')
                ->label('样式')
                ->options(['primary' => '主色强调', 'subtle' => '低调'])
                ->default('primary')
                ->native(false)
                ->required(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title'        => ['required', 'string', 'max:120'],
            'description'  => ['nullable', 'string', 'max:300'],
            'button_label' => ['required', 'string', 'max:30'],
            'button_url'   => ['nullable', 'string', 'max:500'],
            'style'        => ['required', 'string', 'in:primary,subtle'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function defaults(): array
    {
        return [
            'title'        => '',
            'description'  => '',
            'button_label' => '预约咨询',
            'button_url'   => '',
            'style'        => 'primary',
        ];
    }
}
