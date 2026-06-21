<?php

namespace Filamentboot\FilamentbootSite\Filament\Resources\SiteSolutionResource\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filamentboot\FilamentbootSite\Filament\Resources\SiteSolutionResource;

/**
 * 智能方案列表页
 */
class ListSiteSolutions extends ListRecords
{
    protected static string $resource = SiteSolutionResource::class;

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
