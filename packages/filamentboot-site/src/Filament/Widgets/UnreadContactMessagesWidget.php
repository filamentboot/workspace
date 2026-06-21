<?php

namespace Filamentboot\FilamentbootSite\Filament\Widgets;

use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Filamentboot\FilamentbootSite\Enums\ContactMessageStatus;
use Filamentboot\FilamentbootSite\Models\ContactMessage;

/**
 * 未读询盘统计 Widget
 *
 * 在 Filament Panel 仪表盘显示未读询盘数，
 * 表未迁移时 try/catch 降级返回空数组（T-10-03-05）。
 */
class UnreadContactMessagesWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 10;

    /**
     * @return array<int, Stat>
     */
    public function getStats(): array
    {
        try {
            $count = ContactMessage::where('status', ContactMessageStatus::UNREAD->value)->count();

            return [
                Stat::make('未读询盘', $count)
                    ->description('待处理的客户留言')
                    ->color('warning')
                    ->icon('heroicon-o-envelope'),
            ];
        } catch (\Throwable) {
            // site_contact_messages 表未迁移时静默降级
            return [];
        }
    }
}
