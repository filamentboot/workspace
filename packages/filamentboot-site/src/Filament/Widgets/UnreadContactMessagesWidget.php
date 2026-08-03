<?php

namespace Filamentboot\FilamentbootSite\Filament\Widgets;

use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Filamentboot\FilamentbootSite\Enums\ContactMessageStatus;
use Filamentboot\FilamentbootSite\Filament\Pages\SiteSettingsPage;
use Filamentboot\FilamentbootSite\Models\ContactMessage;
use Filamentboot\FilamentbootSite\Services\SiteHealthCheck;

/**
 * 官网概览 Widget
 *
 * 在 Filament Panel 仪表盘显示未读询盘数与站点发布前健康检查结果。
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
        $stats = [];

        try {
            $count = ContactMessage::where('status', ContactMessageStatus::UNREAD->value)->count();

            $stats[] = Stat::make('未读询盘', $count)
                ->description('待处理的客户留言')
                ->color($count > 0 ? 'warning' : 'success')
                ->icon('heroicon-o-envelope');
        } catch (\Throwable) {
            // site_contact_messages 表未迁移时静默降级
        }

        // 站点发布前健康检查：把「配置缺失导致前台静默少一块」变成可见待办
        $missing = app(SiteHealthCheck::class)->missing();

        $stats[] = Stat::make('站点信息待补齐', count($missing))
            ->description($missing === [] ? '已达到发布标准' : implode('、', array_slice($missing, 0, 3)).(count($missing) > 3 ? ' 等' : ''))
            ->color($missing === [] ? 'success' : 'danger')
            ->icon($missing === [] ? 'heroicon-o-check-badge' : 'heroicon-o-exclamation-triangle')
            // 用 Page::getUrl() 而非写死路由名，避免绑死 panel id
            ->url(rescue(fn () => SiteSettingsPage::getUrl(), null, report: false));

        return $stats;
    }
}
