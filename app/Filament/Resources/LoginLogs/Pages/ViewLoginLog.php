<?php

namespace App\Filament\Resources\LoginLogs\Pages;

use App\Filament\Resources\LoginLogs\LoginLogResource;
use Filament\Resources\Pages\ViewRecord;

/**
 * 查看登录日志页
 */
class ViewLoginLog extends ViewRecord
{
    protected static string $resource = LoginLogResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
