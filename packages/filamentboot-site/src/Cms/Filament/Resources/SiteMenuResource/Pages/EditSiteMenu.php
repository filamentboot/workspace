<?php

namespace Filamentboot\FilamentbootSite\Cms\Filament\Resources\SiteMenuResource\Pages;

use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Filamentboot\FilamentbootSite\Cms\Filament\Resources\SiteMenuResource;

/**
 * 编辑前台导航页
 */
class EditSiteMenu extends EditRecord
{
    protected static string $resource = SiteMenuResource::class;

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
