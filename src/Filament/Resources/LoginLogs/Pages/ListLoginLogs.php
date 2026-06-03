<?php

namespace FilamentAdmin\Filament\Resources\LoginLogs\Pages;

use Filament\Actions\ExportAction;
use Filament\Resources\Pages\ListRecords;
use FilamentAdmin\Filament\Exporters\LoginLogExporter;
use FilamentAdmin\Filament\Resources\LoginLogs\LoginLogResource;

/**
 * 登录日志列表页
 */
class ListLoginLogs extends ListRecords
{
    protected static string $resource = LoginLogResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ExportAction::make()
                ->exporter(LoginLogExporter::class)
                ->label('导出'),
        ];
    }
}
