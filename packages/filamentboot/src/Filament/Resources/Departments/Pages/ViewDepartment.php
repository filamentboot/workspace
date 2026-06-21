<?php

namespace Filamentboot\Filament\Resources\Departments\Pages;

use Filament\Resources\Pages\ViewRecord;
use Filamentboot\Filament\Resources\Departments\DepartmentResource;

/**
 * 查看部门详情页
 */
class ViewDepartment extends ViewRecord
{
    protected static string $resource = DepartmentResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
