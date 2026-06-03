<?php

namespace FilamentAdmin\Filament\Resources\AdminUsers\Pages;

use Filament\Actions\CreateAction;
use Filament\Actions\ExportAction;
use Filament\Resources\Pages\ListRecords;
use FilamentAdmin\Filament\Exporters\AdminUserExporter;
use FilamentAdmin\Filament\Resources\AdminUsers\AdminUserResource;

/**
 * 管理员列表页
 */
class ListAdminUsers extends ListRecords
{
    protected static string $resource = AdminUserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
            ExportAction::make()
                ->exporter(AdminUserExporter::class)
                ->label('导出'),
        ];
    }
}
