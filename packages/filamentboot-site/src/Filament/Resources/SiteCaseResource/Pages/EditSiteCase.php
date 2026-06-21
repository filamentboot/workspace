<?php

namespace Filamentboot\FilamentbootSite\Filament\Resources\SiteCaseResource\Pages;

use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;
use Filamentboot\FilamentbootSite\Filament\Resources\SiteCaseResource;

/**
 * 编辑装修案例页
 */
class EditSiteCase extends EditRecord
{
    protected static string $resource = SiteCaseResource::class;

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
