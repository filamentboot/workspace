<?php

namespace Filamentboot\FilamentbootSite\Filament\Resources\SiteTagResource\Pages;

use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Filamentboot\FilamentbootSite\Filament\Resources\SiteTagResource;

/**
 * 编辑标签页
 */
class EditSiteTag extends EditRecord
{
    protected static string $resource = SiteTagResource::class;

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
