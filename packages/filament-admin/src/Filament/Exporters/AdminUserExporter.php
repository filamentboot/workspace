<?php

namespace FilamentAdmin\Filament\Exporters;

use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;
use FilamentAdmin\Models\AdminUser;

/**
 * 管理员用户数据导出器
 */
class AdminUserExporter extends Exporter
{
    /** @var class-string<AdminUser> */
    protected static ?string $model = AdminUser::class;

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
            ExportColumn::make('account')
                ->label('账号'),
            ExportColumn::make('nickname')
                ->label('昵称'),
            ExportColumn::make('email')
                ->label('邮箱'),
            ExportColumn::make('mobile')
                ->label('手机号'),
            ExportColumn::make('department.name')
                ->label('所属部门'),
            ExportColumn::make('status')
                ->label('状态')
                ->formatStateUsing(fn (string $state): string => match ($state) {
                    'active'   => '启用',
                    'inactive' => '禁用',
                    default    => $state,
                }),
            ExportColumn::make('last_login_at')
                ->label('最后登录时间'),
            ExportColumn::make('last_login_ip')
                ->label('最后登录 IP'),
            ExportColumn::make('created_at')
                ->label('创建时间'),
        ];
    }

    /**
     * 导出完成通知标题
     */
    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = '管理员用户导出完成，共 '.number_format($export->successful_rows).' 条记录。';

        if ($failedRowsCount = $export->getFailedRowsCount()) {
            $body .= ' 其中 '.number_format($failedRowsCount).' 条失败。';
        }

        return $body;
    }
}
