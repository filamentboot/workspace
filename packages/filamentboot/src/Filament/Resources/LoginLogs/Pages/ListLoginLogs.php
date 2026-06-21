<?php

namespace FilamentAdmin\Filament\Resources\LoginLogs\Pages;

use Filament\Actions\ExportAction;
use Filament\Resources\Pages\ListRecords;
use FilamentAdmin\Filament\Exporters\LoginLogExporter;
use FilamentAdmin\Filament\Resources\LoginLogs\LoginLogResource;
use FilamentAdmin\Services\ActivityLogger;

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
                ->label('导出')
                ->authorize('export_login_log')
                ->after(function (): void {
                    $causer = app(ActivityLogger::class)->currentCauser();

                    if ($causer) {
                        activity('admin')
                            ->causedBy($causer)
                            ->withProperties(['action' => 'export', 'model' => 'LoginLog'])
                            ->event('export')
                            ->log('导出登录日志数据');
                    }
                }),
        ];
    }
}
