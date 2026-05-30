<?php

namespace App\Filament\Resources\LoginLogs\Pages;

use App\Filament\Resources\LoginLogs\LoginLogResource;
use Filament\Resources\Pages\ListRecords;

/**
 * 登录日志列表页
 */
class ListLoginLogs extends ListRecords
{
    protected static string $resource = LoginLogResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
