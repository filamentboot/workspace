<?php

namespace FilamentAdmin\Filament\Exporters;

use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;
use FilamentAdmin\Models\LoginLog;

/**
 * 登录日志数据导出器
 */
class LoginLogExporter extends Exporter
{
    /** @var class-string<LoginLog> */
    protected static ?string $model = LoginLog::class;

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
            ExportColumn::make('adminUser.nickname')
                ->label('用户昵称'),
            ExportColumn::make('username')
                ->label('登录账号'),
            ExportColumn::make('status')
                ->label('状态')
                ->formatStateUsing(fn (string $state): string => match ($state) {
                    'success' => '成功',
                    'failed'  => '失败',
                    default   => $state,
                }),
            ExportColumn::make('ip_address')
                ->label('IP 地址'),
            ExportColumn::make('failure_reason')
                ->label('失败原因'),
            ExportColumn::make('created_at')
                ->label('登录时间'),
        ];
    }

    /**
     * 导出完成通知标题
     */
    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = '登录日志导出完成，共 '.number_format($export->successful_rows).' 条记录。';

        if ($failedRowsCount = $export->getFailedRowsCount()) {
            $body .= ' 其中 '.number_format($failedRowsCount).' 条失败。';
        }

        return $body;
    }
}
