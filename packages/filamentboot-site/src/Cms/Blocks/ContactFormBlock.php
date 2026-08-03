<?php

namespace Filamentboot\FilamentbootSite\Cms\Blocks;

use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;

/**
 * 内嵌询盘表单区块（#12）
 *
 * 把询盘表单直接放进页面正文，用于落地页这种「不希望访客再去点悬浮按钮」的场景。
 *
 * source 是 A1 的转化入口标识：同一个表单出现在不同页面时，
 * 靠它区分线索是从哪个落地页进来的。字符集与 ContactForm::normalizedSource()
 * 的过滤规则保持一致，否则填了也会被入库时剥掉。
 */
class ContactFormBlock extends AbstractBlock
{
    public function key(): string
    {
        return 'contact-form';
    }

    public function label(): string
    {
        return '询盘表单';
    }

    /**
     * @return array<int, mixed>
     */
    public function schema(): array
    {
        return [
            TextInput::make('title')
                ->label('标题')
                ->maxLength(120)
                ->default('留下联系方式'),
            Textarea::make('description')
                ->label('说明文字')
                ->rows(2)
                ->maxLength(300),
            TextInput::make('source')
                ->label('来源标识')
                ->maxLength(50)
                ->rules(['regex:/^[a-z0-9\-]*$/'])
                ->helperText('用于区分线索来自哪个页面，只允许小写字母、数字与连字符，如 landing-spring'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title'       => ['nullable', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:300'],
            'source'      => ['nullable', 'string', 'max:50', 'regex:/^[a-z0-9\-]*$/'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function defaults(): array
    {
        return [
            'title'       => '留下联系方式',
            'description' => '',
            'source'      => '',
        ];
    }
}
