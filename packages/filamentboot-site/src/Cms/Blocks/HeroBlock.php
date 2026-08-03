<?php

namespace Filamentboot\FilamentbootSite\Cms\Blocks;

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;

/**
 * 首屏区块（#12）
 *
 * 大标题 + 副标题 + 背景图 + 主 CTA，用于页面顶部。
 */
class HeroBlock extends AbstractBlock
{
    public function key(): string
    {
        return 'hero';
    }

    public function label(): string
    {
        return '首屏横幅';
    }

    /**
     * @return array<int, mixed>
     */
    public function schema(): array
    {
        return [
            TextInput::make('title')
                ->label('主标题')
                ->required()
                ->maxLength(120),
            Textarea::make('subtitle')
                ->label('副标题')
                ->rows(2)
                ->maxLength(300),
            FileUpload::make('image')
                ->label('背景图')
                ->image()
                ->disk($this->defaultDisk())
                ->helperText('建议 1920×800 以上'),
            TextInput::make('image_alt')
                ->label('背景图替代文字')
                ->maxLength(200)
                ->helperText('图片加载失败或读屏软件使用，上传图片后必填'),
            TextInput::make('cta_label')
                ->label('按钮文字')
                ->maxLength(30),
            TextInput::make('cta_url')
                ->label('按钮链接')
                ->maxLength(500)
                ->helperText('站内路径（如 /contact）或完整外链'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title'    => ['required', 'string', 'max:120'],
            'subtitle' => ['nullable', 'string', 'max:300'],
            'image'    => ['nullable', 'string', 'max:1024'],
            // 有图必须有 alt：无障碍要求，也影响图片搜索收录
            'image_alt' => ['nullable', 'string', 'max:200', 'required_with:image'],
            'cta_label' => ['nullable', 'string', 'max:30'],
            'cta_url'   => ['nullable', 'string', 'max:500', 'required_with:cta_label'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function defaults(): array
    {
        return [
            'title'     => '',
            'subtitle'  => '',
            'image'     => null,
            'image_alt' => '',
            'cta_label' => '',
            'cta_url'   => '',
        ];
    }
}
