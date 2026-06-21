<?php

namespace FilamentAdmin\Filament\Resources\Menus\Pages;

use FilamentAdmin\Filament\Resources\Menus\MenuResource;
use FilamentAdmin\Models\Menu;
use FilamentAdmin\Services\ActivityLogger;
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

    /**
     * 覆盖树排序，排序完成后写入操作日志
     *
     * @param  array<int, mixed>|null  $list
     * @return array<string, mixed>
     */
    public function updateTree(?array $list = null): array
    {
        $before = $this->buildReorderSnapshot();

        $result = parent::updateTree($list);

        if ($result['reload'] ?? false) {
            $after = $this->buildReorderSnapshot();
            $this->logReorderActivity($before, $after);
        }

        return $result;
    }

    /**
     * 构建当前菜单排序快照（按 sort 升序）
     *
     * @return array<int, array<string, mixed>>
     */
    protected function buildReorderSnapshot(): array
    {
        return Menu::query()
            ->orderBy('sort')
            ->get(['id', 'parent_id', 'title', 'sort'])
            ->map(fn (Menu $menu): array => [
                'id'        => $menu->id,
                'parent_id' => $menu->parent_id,
                'title'     => $menu->title,
                'sort'      => $menu->sort,
            ])
            ->values()
            ->all();
    }

    /**
     * 记录菜单拖拽排序操作日志
     *
     * @param  array<int, array<string, mixed>>  $before
     * @param  array<int, array<string, mixed>>  $after
     */
    protected function logReorderActivity(array $before, array $after): void
    {
        $logger  = app(ActivityLogger::class);
        $causer  = $logger->currentCauser();
        $subject = Menu::query()->orderBy('sort')->first();

        if (! $causer || ! $subject) {
            return;
        }

        $logger->logChanges(
            causer: $causer,
            subject: $subject,
            action: 'reordered',
            before: ['order' => $before],
            after: ['order' => $after],
        );
    }
}
