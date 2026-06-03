<?php

namespace FilamentAdmin\Filament\Widgets;

use Filament\Widgets\Widget;
use Illuminate\Database\Eloquent\Collection;
use Spatie\Activitylog\Models\Activity;

/**
 * 最近操作记录 Widget
 *
 * 显示最近 10 条操作日志。
 */
class RecentActivityWidget extends Widget
{
    protected string $view = 'filament.widgets.recent-activity-widget';

    protected static ?int $sort = 4;

    /** @var int|string|array<int|string> */
    protected int|string|array $columnSpan = 'full';

    /**
     * 获取最近操作记录
     *
     * @return Collection<int, Activity>
     */
    public function getRecentActivities(): Collection
    {
        return Activity::query()
            ->with('causer')
            ->latest()
            ->limit(10)
            ->get();
    }
}
