<?php

namespace Filamentboot\Filament\Resources\Departments\Pages;

use Filament\Actions\CreateAction;
use Filament\Actions\ExportAction;
use Filament\Resources\Pages\ListRecords;
use Filamentboot\Filament\Exporters\DepartmentExporter;
use Filamentboot\Filament\Resources\Departments\DepartmentResource;
use Filamentboot\Services\ActivityLogger;

/**
 * 部门列表页
 */
class ListDepartments extends ListRecords
{
    protected static string $resource = DepartmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
            ExportAction::make()
                ->exporter(DepartmentExporter::class)
                ->label('导出')
                ->authorize('export_department')
                ->after(function (): void {
                    $causer = app(ActivityLogger::class)->currentCauser();

                    if ($causer) {
                        activity('admin')
                            ->causedBy($causer)
                            ->withProperties(['action' => 'export', 'model' => 'Department'])
                            ->event('export')
                            ->log('导出部门数据');
                    }
                }),
        ];
    }
}
