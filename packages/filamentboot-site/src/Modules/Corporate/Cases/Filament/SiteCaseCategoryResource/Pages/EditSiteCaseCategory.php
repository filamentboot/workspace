<?php

namespace Filamentboot\FilamentbootSite\Modules\Corporate\Cases\Filament\SiteCaseCategoryResource\Pages;

use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Filamentboot\FilamentbootSite\Modules\Corporate\Cases\Filament\SiteCaseCategoryResource;

/**
 * 编辑案例分类页
 */
class EditSiteCaseCategory extends EditRecord
{
    protected static string $resource = SiteCaseCategoryResource::class;

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
