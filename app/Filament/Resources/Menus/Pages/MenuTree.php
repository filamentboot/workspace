<?php

namespace App\Filament\Resources\Menus\Pages;

use App\Filament\Resources\Menus\MenuResource;
use Illuminate\Database\Eloquent\Model;
use SolutionForest\FilamentTree\Resources\Pages\TreePage;

/**
 * 菜单规则树形管理页
 */
class MenuTree extends TreePage
{
    protected static string $resource = MenuResource::class;

    /**
     * 默认全部折叠
     */
    public function getNodeCollapsedState(?Model $record = null): bool
    {
        return true;
    }
}
