<?php

namespace Filamentboot\FilamentbootSite\Modules\Corporate\Products\Filament\SiteProductCategoryResource\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filamentboot\FilamentbootSite\Modules\Corporate\Products\Filament\SiteProductCategoryResource;

/**
 * 产品分类列表页
 */
class ListSiteProductCategories extends ListRecords
{
    protected static string $resource = SiteProductCategoryResource::class;

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
