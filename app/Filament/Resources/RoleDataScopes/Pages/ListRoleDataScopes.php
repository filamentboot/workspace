<?php

namespace App\Filament\Resources\RoleDataScopes\Pages;

use App\Filament\Resources\RoleDataScopes\RoleDataScopeResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

/**
 * 数据权限列表页
 */
class ListRoleDataScopes extends ListRecords
{
    protected static string $resource = RoleDataScopeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->mutateDataUsing(fn (array $data): array => RoleDataScopeResource::normalizeFormData($data)),
        ];
    }
}
