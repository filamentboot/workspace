<?php

namespace FilamentAdmin\Filament\Resources\Departments\Pages;

use Filament\Actions\CreateAction;
use Filament\Actions\ExportAction;
use Filament\Resources\Pages\ListRecords;
use FilamentAdmin\Filament\Exporters\DepartmentExporter;
use FilamentAdmin\Filament\Resources\Departments\DepartmentResource;

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
                ->label('导出'),
        ];
    }
}
