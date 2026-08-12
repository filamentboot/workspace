<?php

namespace Filamentboot\FilamentbootSite\Modules\Corporate\Packages\Filament\SitePackageResource\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filamentboot\FilamentbootSite\Modules\Corporate\Packages\Filament\SitePackageResource;

/**
 * 全屋套餐列表页
 */
class ListSitePackages extends ListRecords
{
    protected static string $resource = SitePackageResource::class;

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
