<?php

namespace FilamentAdmin\Filament\Widgets;

use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use FilamentAdmin\Models\AdminUser;
use FilamentAdmin\Models\LoginLog;
use Spatie\Permission\Models\Role;

/**
 * 系统状态概览 Widget
 *
 * 显示管理员总数、角色数、今日登录次数。
 */
class SystemStatsWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 2;

    /**
     * @return array<int, Stat>
     */
    public function getStats(): array
    {
        return [
            Stat::make('管理员总数', AdminUser::query()->count())
                ->icon('heroicon-o-users'),
            Stat::make('角色总数', Role::query()->where('guard_name', 'admin')->count())
                ->icon('heroicon-o-shield-check'),
            Stat::make('今日登录次数', LoginLog::query()
                ->where('status', 'success')
                ->whereDate('created_at', today())
                ->count())
                ->icon('heroicon-o-arrow-right-on-rectangle'),
        ];
    }
}
