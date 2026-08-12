<?php

namespace Filamentboot\FilamentbootSite\Modules\Corporate\Banners\Filament\SiteBannerResource\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filamentboot\FilamentbootSite\Modules\Corporate\Banners\Filament\SiteBannerResource;

/**
 * 幻灯片列表页
 */
class ListSiteBanners extends ListRecords
{
    protected static string $resource = SiteBannerResource::class;

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
