<?php

namespace Filamentboot\FilamentbootSite\Filament\Resources\SiteTagResource\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filamentboot\FilamentbootSite\Filament\Resources\SiteTagResource;

/**
 * 标签列表页
 */
class ListSiteTags extends ListRecords
{
    protected static string $resource = SiteTagResource::class;

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
