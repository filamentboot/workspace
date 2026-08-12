<?php

namespace Filamentboot\FilamentbootSite\Modules\Corporate\Products\Filament\SiteProductCategoryResource\Pages;

use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Filamentboot\FilamentbootSite\Modules\Corporate\Products\Filament\SiteProductCategoryResource;

/**
 * 编辑产品分类页
 */
class EditSiteProductCategory extends EditRecord
{
    protected static string $resource = SiteProductCategoryResource::class;

    /**
     * @return array<int, mixed>
     */
    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
