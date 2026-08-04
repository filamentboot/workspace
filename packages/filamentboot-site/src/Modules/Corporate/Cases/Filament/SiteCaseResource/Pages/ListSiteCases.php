<?php

namespace Filamentboot\FilamentbootSite\Modules\Corporate\Cases\Filament\SiteCaseResource\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filamentboot\FilamentbootSite\Modules\Corporate\Cases\Filament\SiteCaseResource;

/**
 * 装修案例列表页
 */
class ListSiteCases extends ListRecords
{
    protected static string $resource = SiteCaseResource::class;

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
