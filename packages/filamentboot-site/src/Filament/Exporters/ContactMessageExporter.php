<?php

namespace Filamentboot\FilamentbootSite\Filament\Exporters;

use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;
use Filamentboot\FilamentbootSite\Enums\ContactMessageStatus;
use Filamentboot\FilamentbootSite\Models\ContactMessage;

/**
 * 询盘数据导出器（A4）
 *
 * 含 A1 的来源与 UTM 渠道字段，导出结果可直接对接投放侧做效果核算。
 *
 * ⚠️ 导出内容是访客 PII（姓名 + 电话）批量外流，
 * 因此列表页的 ExportAction 用独立权限点 export_contact_message 门住并记操作日志。
 */
class ContactMessageExporter extends Exporter
{
    /** @var class-string<ContactMessage> */
    protected static ?string $model = ContactMessage::class;

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
                ->label('姓名'),
            ExportColumn::make('phone')
                ->label('电话'),
            ExportColumn::make('message')
                ->label('留言'),
            ExportColumn::make('status')
                ->label('状态')
                ->formatStateUsing(fn (mixed $state): string => $state instanceof ContactMessageStatus
                    ? $state->label()
                    : (string) (ContactMessageStatus::tryFrom((string) $state)?->label() ?? $state)),
            ExportColumn::make('assignee.nickname')
                ->label('跟进人'),
            ExportColumn::make('source')
                ->label('转化入口')
                ->formatStateUsing(fn (mixed $state): string => $state === null
                    ? ''
                    : (string) (config('filamentboot-site.contact.sources')[$state] ?? $state)),
            ExportColumn::make('utm_source')
                ->label('渠道来源'),
            ExportColumn::make('utm_medium')
                ->label('渠道媒介'),
            ExportColumn::make('utm_campaign')
                ->label('推广活动'),
            ExportColumn::make('utm_term')
                ->label('关键词'),
            ExportColumn::make('utm_content')
                ->label('创意标识'),
            ExportColumn::make('landing_url')
                ->label('首次落地页'),
            ExportColumn::make('referer')
                ->label('来源页'),
            ExportColumn::make('ip')
                ->label('IP 地址'),
            ExportColumn::make('created_at')
                ->label('提交时间'),
        ];
    }

    /**
     * 导出完成通知内容
     */
    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = '询盘数据导出完成，共 '.number_format($export->successful_rows).' 条记录。';

        if ($failedRowsCount = $export->getFailedRowsCount()) {
            $body .= ' 其中 '.number_format($failedRowsCount).' 条失败。';
        }

        return $body;
    }
}
