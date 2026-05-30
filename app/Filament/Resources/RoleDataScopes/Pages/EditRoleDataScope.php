<?php

namespace App\Filament\Resources\RoleDataScopes\Pages;

use App\Filament\Resources\RoleDataScopes\RoleDataScopeResource;
use Filament\Resources\Pages\EditRecord;

/**
 * 编辑数据权限页
 */
class EditRoleDataScope extends EditRecord
{
    protected static string $resource = RoleDataScopeResource::class;

    /**
     * 规范化保存数据
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        return RoleDataScopeResource::normalizeFormData($data);
    }
}
