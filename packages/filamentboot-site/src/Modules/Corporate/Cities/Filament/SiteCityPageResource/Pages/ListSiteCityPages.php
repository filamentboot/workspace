<?php

namespace Filamentboot\FilamentbootSite\Modules\Corporate\Cities\Filament\SiteCityPageResource\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filamentboot\FilamentbootSite\Modules\Corporate\Cities\Filament\SiteCityPageResource;

/**
 * 城市页列表
 */
class ListSiteCityPages extends ListRecords
{
    protected static string $resource = SiteCityPageResource::class;

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
