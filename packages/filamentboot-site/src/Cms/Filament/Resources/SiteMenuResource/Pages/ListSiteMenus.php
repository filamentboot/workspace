<?php

namespace Filamentboot\FilamentbootSite\Cms\Filament\Resources\SiteMenuResource\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filamentboot\FilamentbootSite\Cms\Filament\Resources\SiteMenuResource;

/**
 * 前台导航列表页
 */
class ListSiteMenus extends ListRecords
{
    protected static string $resource = SiteMenuResource::class;

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
