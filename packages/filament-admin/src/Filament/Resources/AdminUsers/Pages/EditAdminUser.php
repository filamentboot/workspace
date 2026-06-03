<?php

namespace FilamentAdmin\Filament\Resources\AdminUsers\Pages;

use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;
use FilamentAdmin\Filament\Resources\AdminUsers\AdminUserResource;
use FilamentAdmin\Models\AdminUser;
use FilamentAdmin\Services\ActivityLogger;

/**
 * 编辑管理员页
 */
class EditAdminUser extends EditRecord
{
    protected static string $resource = AdminUserResource::class;

    /**
     * 保存前角色快照
     *
     * @var list<string>
     */
    protected array $beforeRoles = [];

    /**
     * 是否记录密码重置
     */
    protected bool $shouldLogPasswordReset = false;

    /**
     * 过滤无权提交的敏感字段
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (
            blank($data['password'] ?? null) ||
            ! (auth('admin')->user()?->can('resetPassword', $this->getRecord()) ?? false)
        ) {
            $this->shouldLogPasswordReset = false;
            unset($data['password']);

            return $data;
        }

        $this->shouldLogPasswordReset = true;

        return $data;
    }

    protected function beforeSave(): void
    {
        $record = $this->getRecord();

        if (! $record instanceof AdminUser) {
            $this->beforeRoles = [];

            return;
        }

        $record->loadMissing('roles');

        $this->beforeRoles = $record->roles->pluck('name')->sort()->values()->all();
    }

    protected function afterSave(): void
    {
        $logger = app(ActivityLogger::class);
        $causer = $logger->currentCauser();
        $record = $this->getRecord()->fresh('roles');

        if (! $causer instanceof AdminUser || ! $record instanceof AdminUser) {
            return;
        }

        $afterRoles = $record->roles->pluck('name')->sort()->values()->all();

        if ($this->beforeRoles !== $afterRoles) {
            $logger->log(
                causer: $causer,
                subject: $record,
                action: 'roles_updated',
                before: ['roles' => $this->beforeRoles],
                after: ['roles' => $afterRoles],
            );
        }

        if (! $this->shouldLogPasswordReset) {
            return;
        }

        $logger->log(
            causer: $causer,
            subject: $record,
            action: 'password_reset',
            before: [],
            after: ['password_changed' => true],
        );

        $this->shouldLogPasswordReset = false;
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }
}
