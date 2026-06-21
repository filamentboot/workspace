<?php

namespace Filamentboot\Filament\Resources\LoginLogs\Pages;

use Filament\Resources\Pages\ViewRecord;
use Filamentboot\Filament\Resources\LoginLogs\LoginLogResource;

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
