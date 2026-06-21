<?php

namespace Filamentboot\Filament\Resources\AdminUsers\Pages;

use Filament\Actions\CreateAction;
use Filament\Actions\ExportAction;
use Filament\Resources\Pages\ListRecords;
use Filamentboot\Filament\Exporters\AdminUserExporter;
use Filamentboot\Filament\Resources\AdminUsers\AdminUserResource;
use Filamentboot\Services\ActivityLogger;

/**
 * 管理员列表页
 */
class ListAdminUsers extends ListRecords
{
    protected static string $resource = AdminUserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
            ExportAction::make()
                ->exporter(AdminUserExporter::class)
                ->label('导出')
                ->authorize('export_admin_user')
                ->after(function (): void {
                    $causer = app(ActivityLogger::class)->currentCauser();

                    if ($causer) {
                        activity('admin')
                            ->causedBy($causer)
                            ->withProperties(['action' => 'export', 'model' => 'AdminUser'])
                            ->event('export')
                            ->log('导出管理员用户数据');
                    }
                }),
        ];
    }
}
