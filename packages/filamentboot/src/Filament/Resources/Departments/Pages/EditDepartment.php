<?php

namespace FilamentAdmin\Filament\Resources\Departments\Pages;

use Filament\Actions\DeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;
use FilamentAdmin\Filament\Resources\Departments\DepartmentResource;

/**
 * 编辑部门页
 */
class EditDepartment extends EditRecord
{
    protected static string $resource = DepartmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
            RestoreAction::make(),
        ];
    }
}
