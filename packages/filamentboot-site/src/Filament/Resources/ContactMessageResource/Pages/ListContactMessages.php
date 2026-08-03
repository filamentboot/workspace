<?php

namespace Filamentboot\FilamentbootSite\Filament\Resources\ContactMessageResource\Pages;

use Filament\Actions\ExportAction;
use Filament\Resources\Pages\ListRecords;
use Filamentboot\FilamentbootSite\Filament\Exporters\ContactMessageExporter;
use Filamentboot\FilamentbootSite\Filament\Resources\ContactMessageResource;
use Filamentboot\Services\ActivityLogger;

/**
 * 询盘列表页（只读，无新建按钮，含导出）
 */
class ListContactMessages extends ListRecords
{
    protected static string $resource = ContactMessageResource::class;

    /**
     * 头部 Action：仅导出（canCreate=false，不提供新建）
     *
     * 导出是访客 PII 批量外流，独立权限点 + 操作日志两条都不能省，
     * 与主包 ListLoginLogs 的做法保持一致。
     *
     * @return array<int, mixed>
     */
    protected function getHeaderActions(): array
    {
        return [
            ExportAction::make()
                ->exporter(ContactMessageExporter::class)
                ->label('导出')
                ->authorize('export_contact_message')
                ->after(function (): void {
                    $causer = app(ActivityLogger::class)->currentCauser();

                    if ($causer) {
                        activity('admin')
                            ->causedBy($causer)
                            ->withProperties(['action' => 'export', 'model' => 'ContactMessage'])
                            ->event('export')
                            ->log('导出询盘数据');
                    }
                }),
        ];
    }
}
