<?php

namespace LaravelStack\FilamentAdminSite\Filament\Resources\SiteProductResource\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use LaravelStack\FilamentAdminSite\Filament\Resources\SiteProductResource;

/**
 * 智能产品列表页
 */
class ListSiteProducts extends ListRecords
{
    protected static string $resource = SiteProductResource::class;

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
