<?php

namespace FilamentAdmin\Filament\Exporters;

use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;
use FilamentAdmin\Models\Department;

/**
 * 部门数据导出器
 */
class DepartmentExporter extends Exporter
{
    /** @var class-string<Department> */
    protected static ?string $model = Department::class;

    /**
     * 定义导出列
     *
     * @return array<ExportColumn>
     */
    public static function getColumns(): array
    {
        return [
            ExportColumn::make('id')
                ->label('ID'),
            ExportColumn::make('name')
                ->label('部门名称'),
            ExportColumn::make('code')
                ->label('部门编码'),
            ExportColumn::make('parent.name')
                ->label('上级部门'),
            ExportColumn::make('leader.nickname')
                ->label('负责人'),
            ExportColumn::make('sort')
                ->label('排序'),
            ExportColumn::make('is_active')
                ->label('状态')
                ->formatStateUsing(fn (bool $state): string => $state ? '启用' : '禁用'),
            ExportColumn::make('created_at')
                ->label('创建时间'),
        ];
    }

    /**
     * 导出完成通知标题
     */
    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = '部门导出完成，共 '.number_format($export->successful_rows).' 条记录。';

        if ($failedRowsCount = $export->getFailedRowsCount()) {
            $body .= ' 其中 '.number_format($failedRowsCount).' 条失败。';
        }

        return $body;
    }
}
