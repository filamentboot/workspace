<?php

namespace Filamentboot\FilamentbootSite\Cms\Filament\Resources\SiteRedirectResource\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filamentboot\FilamentbootSite\Cms\Filament\Resources\SiteRedirectResource;

/**
 * URL 重定向列表页
 *
 * 新建与编辑走模态而不是独立页面：重定向只有三个字段。
 */
class ListSiteRedirects extends ListRecords
{
    protected static string $resource = SiteRedirectResource::class;

    /**
     * @return array<int, mixed>
     */
    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
