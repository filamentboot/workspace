<?php

namespace Filamentboot\FilamentbootSite\Filament\Resources\SitePageResource\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filamentboot\FilamentbootSite\Filament\Resources\SitePageResource;

/**
 * 静态页面列表页
 */
class ListSitePages extends ListRecords
{
    protected static string $resource = SitePageResource::class;

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
