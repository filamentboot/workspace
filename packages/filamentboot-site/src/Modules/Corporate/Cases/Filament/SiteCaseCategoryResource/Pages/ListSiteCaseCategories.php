<?php

namespace Filamentboot\FilamentbootSite\Modules\Corporate\Cases\Filament\SiteCaseCategoryResource\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filamentboot\FilamentbootSite\Modules\Corporate\Cases\Filament\SiteCaseCategoryResource;

/**
 * 案例分类列表页
 */
class ListSiteCaseCategories extends ListRecords
{
    protected static string $resource = SiteCaseCategoryResource::class;

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
