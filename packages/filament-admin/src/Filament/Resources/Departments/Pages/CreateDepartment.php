<?php

namespace FilamentAdmin\Filament\Resources\Departments\Pages;

use Filament\Resources\Pages\CreateRecord;
use FilamentAdmin\Filament\Resources\Departments\DepartmentResource;

/**
 * 创建部门页
 */
class CreateDepartment extends CreateRecord
{
    protected static string $resource = DepartmentResource::class;
}
