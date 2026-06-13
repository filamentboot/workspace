<?php

namespace LaravelStack\FilamentAdminSite\Filament\Resources\SitePageResource\Pages;

use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;
use LaravelStack\FilamentAdminSite\Filament\Resources\SitePageResource;

/**
 * 编辑静态页面页
 */
class EditSitePage extends EditRecord
{
    protected static string $resource = SitePageResource::class;

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
