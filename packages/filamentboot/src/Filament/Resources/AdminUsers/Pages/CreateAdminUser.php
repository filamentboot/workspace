<?php

namespace FilamentAdmin\Filament\Resources\AdminUsers\Pages;

use Filament\Resources\Pages\CreateRecord;
use FilamentAdmin\Filament\Resources\AdminUsers\AdminUserResource;
use FilamentAdmin\Models\AdminUser;
use FilamentAdmin\Services\ActivityLogger;

/**
 * 创建管理员页
 */
class CreateAdminUser extends CreateRecord
{
    protected static string $resource = AdminUserResource::class;

    protected function afterCreate(): void
    {
        $causer = app(ActivityLogger::class)->currentCauser();
        $record = $this->getRecord()->fresh('roles');

        if (! $causer instanceof AdminUser || ! $record instanceof AdminUser) {
            return;
        }

        $roles = $record->roles->pluck('name')->sort()->values()->all();

        if ($roles === []) {
            return;
        }

        app(ActivityLogger::class)->log(
            causer: $causer,
            subject: $record,
            action: 'roles_updated',
            before: [],
            after: ['roles' => $roles],
        );
    }
}
