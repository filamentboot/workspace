<?php

namespace Filamentboot\FilamentbootSite\Cms\Filament\Resources\SitePageResource\Pages;

use Filament\Resources\Pages\CreateRecord;
use Filamentboot\FilamentbootSite\Cms\Filament\Resources\SitePageResource;
use Filamentboot\FilamentbootSite\Cms\Rendering\BlockSanitizer;

/**
 * 创建静态页面页
 */
class CreateSitePage extends CreateRecord
{
    protected static string $resource = SitePageResource::class;

    /**
     * 保存前净化区块 payload（#13）
     *
     * 与 EditSitePage 各写一份而不是抽公共方法：两个钩子名不同
     * （BeforeCreate / BeforeSave），共用只能再包一层，不值得。
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (array_key_exists('blocks', $data)) {
            $data['blocks'] = app(BlockSanitizer::class)->sanitize($data['blocks']);
        }

        return $data;
    }
}
