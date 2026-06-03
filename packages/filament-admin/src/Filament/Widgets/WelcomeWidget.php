<?php

namespace FilamentAdmin\Filament\Widgets;

use Filament\Widgets\Widget;

/**
 * 欢迎卡片 Widget
 *
 * 显示当前管理员信息和系统欢迎语。
 */
class WelcomeWidget extends Widget
{
    protected string $view = 'filament.widgets.welcome-widget';

    protected static ?int $sort = 1;

    /** @var int|string|array<int|string> */
    protected int|string|array $columnSpan = 'full';

    /**
     * 获取当前管理员昵称（优先昵称，回退账号）
     */
    public function getAdminNickname(): string
    {
        $user = auth('admin')->user();

        return $user?->nickname ?? $user?->account ?? '管理员';
    }
}
