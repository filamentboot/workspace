<?php

namespace FilamentAdmin\Filament\Widgets;

use Filament\Widgets\Widget;

/**
 * 常用功能入口 Widget
 *
 * 显示管理员管理、角色权限、菜单管理等快捷入口。
 */
class QuickActionsWidget extends Widget
{
    protected string $view = 'filament.widgets.quick-actions-widget';

    protected static ?int $sort = 3;

    /** @var int|string|array<int|string> */
    protected int|string|array $columnSpan = 'full';

    /**
     * 快捷功能列表
     *
     * @return array<int, array<string, string>>
     */
    public function getActions(): array
    {
        return [
            ['label' => '管理员管理', 'icon' => 'heroicon-o-users',           'url' => '/admin/admin-users'],
            ['label' => '角色权限',   'icon' => 'heroicon-o-shield-check',    'url' => '/admin/shield/roles'],
            ['label' => '菜单管理',   'icon' => 'heroicon-o-bars-3',           'url' => '/admin/menus'],
            ['label' => '部门管理',   'icon' => 'heroicon-o-building-office',  'url' => '/admin/departments'],
        ];
    }
}
