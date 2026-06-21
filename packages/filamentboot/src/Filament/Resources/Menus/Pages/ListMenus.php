<?php

namespace FilamentAdmin\Filament\Resources\Menus\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use FilamentAdmin\Filament\Resources\Menus\MenuResource;

/**
 * 菜单列表页
 */
class ListMenus extends ListRecords
{
    protected static string $resource = MenuResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
