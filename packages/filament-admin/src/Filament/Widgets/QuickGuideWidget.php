<?php

namespace FilamentAdmin\Filament\Widgets;

use Filament\Widgets\Widget;
use FilamentAdmin\Models\AdminUser;

/**
 * 新手引导 Widget
 *
 * 帮助新管理员完成初始配置步骤，完成后可关闭。
 * 关闭状态持久化到 admin_users.onboarding_completed。
 */
class QuickGuideWidget extends Widget
{
    protected string $view = 'filament.widgets.quick-guide-widget';

    protected static ?int $sort = 5;

    /** @var int|string|array<int|string> */
    protected int|string|array $columnSpan = 'full';

    /**
     * 对未完成 onboarding 的管理员可见
     */
    public static function canView(): bool
    {
        $user = auth('admin')->user();

        return $user instanceof AdminUser && ! $user->onboarding_completed;
    }

    /**
     * 关闭引导，将 onboarding_completed 设为 true
     */
    public function dismiss(): void
    {
        $user = auth('admin')->user();

        if ($user instanceof AdminUser) {
            $user->update(['onboarding_completed' => true]);
        }
    }
}
