<?php

namespace Filamentboot\FilamentbootSite\Modules\Corporate\Products\Filament\SiteProductResource\Pages;

use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;
use Filamentboot\FilamentbootSite\Modules\Corporate\Products\Filament\SiteProductResource;

/**
 * 编辑智能产品页
 */
class EditSiteProduct extends EditRecord
{
    protected static string $resource = SiteProductResource::class;

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
