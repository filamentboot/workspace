<?php

namespace App\Filament\Resources\Menus\Pages;

use App\Filament\Resources\Menus\MenuResource;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Livewire\Attributes\On;
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

    /**
     * 覆盖基类的 getRecords()，在 refreshTree 事件触发时先清缓存再重查
     *
     * 基类 HasRecords::getRecords() 有对象级缓存（$this->records），
     * 拖拽排序后 dispatch refreshTree 时缓存未清空，导致 Livewire
     * 重渲染仍使用旧数据，树视图看起来"回弹"到拖拽前的顺序。
     */
    #[On('refreshTree')]
    public function getRecords(): ?Collection
    {
        $this->records = null;

        return parent::getRecords();
    }
}
