<?php

namespace Filamentboot\FilamentbootSite\Filament\Widgets;

use Filament\Facades\Filament;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Filamentboot\FilamentbootSite\Enums\ContactMessageStatus;
use Filamentboot\FilamentbootSite\Filament\Pages\SiteSettingsPage;
use Filamentboot\FilamentbootSite\Models\ContactMessage;
use Filamentboot\FilamentbootSite\Services\SiteHealthCheck;
use Illuminate\Support\Facades\Gate;

/**
 * 官网概览 Widget
 *
 * 在 Filament Panel 仪表盘显示未读询盘数与站点发布前健康检查结果。
 * 表未迁移时 try/catch 降级返回空数组（T-10-03-05）。
 *
 * ⚠️ **两个 Stat 的权限口径不同，闸要分开打**（3.5 期 B 段补）：
 *
 * - **未读询盘数**是线索量，与 `ContactMessageResource` 同一道闸——看不了询盘的人
 *   也不该从看板上看出有多少条。这里此前完全没有判权，任何后台管理员都能看到。
 * - **站点信息待补齐**是配置完整度，任何进得来后台的人看到都无害，也正是它该被看到的地方。
 *
 * 所以不是给整个 Widget 加一道 `canView()` 一刀切——那会把健康检查一起藏掉，
 * 而健康检查恰恰是「配置缺失导致前台静默少一块」的唯一可见提醒。
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

        if ($this->canSeeContactMessages()) {
            try {
                $count = ContactMessage::where('status', ContactMessageStatus::UNREAD->value)->count();

                $stats[] = Stat::make('未读询盘', $count)
                    ->description('待处理的客户留言')
                    ->color($count > 0 ? 'warning' : 'success')
                    ->icon('heroicon-o-envelope');
            } catch (\Throwable) {
                // site_contact_messages 表未迁移时静默降级
            }
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

    /**
     * 当前用户能不能看到询盘线索量
     *
     * 与 `ContactSourceWidget::canView()` 同一道闸，只是这里只挡一个 Stat 不挡整个 Widget。
     */
    protected function canSeeContactMessages(): bool
    {
        $user = Filament::auth()->user();

        return $user !== null
            && Gate::forUser($user)->allows('viewAny', ContactMessage::class);
    }
}
