<?php

namespace FilamentAdmin\Filament\Resources\Menus\Pages;

use Filament\Actions\DeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;
use FilamentAdmin\Filament\Resources\Menus\MenuResource;

/**
 * 编辑菜单页
 */
class EditMenu extends EditRecord
{
    protected static string $resource = MenuResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
            RestoreAction::make(),
        ];
    }
}
