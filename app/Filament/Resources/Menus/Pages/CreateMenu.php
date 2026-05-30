<?php

namespace App\Filament\Resources\Menus\Pages;

use App\Filament\Resources\Menus\MenuResource;
use Filament\Resources\Pages\CreateRecord;

/**
 * 创建菜单页
 */
class CreateMenu extends CreateRecord
{
    protected static string $resource = MenuResource::class;
}
