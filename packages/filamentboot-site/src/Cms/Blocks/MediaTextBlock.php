<?php

namespace Filamentboot\FilamentbootSite\Cms\Blocks;

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;

/**
 * 图文左右分栏区块（#12）
 *
 * 一图一段文字，图片可放左侧或右侧，用于产品/服务介绍。
 */
class MediaTextBlock extends AbstractBlock
{
    public function key(): string
    {
        return 'media-text';
    }

    public function label(): string
    {
        return '图文分栏';
    }

    /**
     * @return array<int, mixed>
     */
    public function schema(): array
    {
        return [
            TextInput::make('title')
                ->label('标题')
                ->required()
                ->maxLength(120),
            Textarea::make('body')
                ->label('正文')
                ->rows(5)
                ->required()
                ->maxLength(2000),
            FileUpload::make('image')
                ->label('配图')
                ->image()
                ->required()
                ->disk($this->defaultDisk()),
            TextInput::make('image_alt')
                ->label('配图替代文字')
                ->required()
                ->maxLength(200),
            Select::make('media_position')
                ->label('图片位置')
                ->options(['left' => '图左文右', 'right' => '图右文左'])
                ->default('left')
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
            'title'          => ['required', 'string', 'max:120'],
            'body'           => ['required', 'string', 'max:2000'],
            'image'          => ['required', 'string', 'max:1024'],
            'image_alt'      => ['required', 'string', 'max:200'],
            'media_position' => ['required', 'string', 'in:left,right'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function defaults(): array
    {
        return [
            'title'          => '',
            'body'           => '',
            'image'          => null,
            'image_alt'      => '',
            'media_position' => 'left',
        ];
    }
}
