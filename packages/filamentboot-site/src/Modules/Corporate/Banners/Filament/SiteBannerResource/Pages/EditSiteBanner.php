<?php

namespace Filamentboot\FilamentbootSite\Modules\Corporate\Banners\Filament\SiteBannerResource\Pages;

use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;
use Filamentboot\FilamentbootSite\Modules\Corporate\Banners\Filament\SiteBannerResource;

/**
 * 编辑幻灯片页
 */
class EditSiteBanner extends EditRecord
{
    protected static string $resource = SiteBannerResource::class;

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
