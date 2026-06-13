<?php

namespace LaravelStack\FilamentAdminSite\Filament\Resources\SiteSolutionResource\Pages;

use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;
use LaravelStack\FilamentAdminSite\Filament\Resources\SiteSolutionResource;

/**
 * 编辑智能方案页
 */
class EditSiteSolution extends EditRecord
{
    protected static string $resource = SiteSolutionResource::class;

    /**
     * @return array<int, mixed>
     */
    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }
}
