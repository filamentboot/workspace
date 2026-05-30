<?php

namespace App\Filament\Resources\Departments\Pages;

use App\Filament\Resources\Departments\DepartmentResource;
use Filament\Resources\Pages\CreateRecord;

/**
 * 创建部门页
 */
class CreateDepartment extends CreateRecord
{
    protected static string $resource = DepartmentResource::class;
}
