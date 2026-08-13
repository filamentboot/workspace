<?php

namespace Filamentboot\Filament\Resources\Menus\Pages;

use Filament\Schemas\Components\Component;
use Filamentboot\Filament\Resources\Menus\MenuResource;
use Filamentboot\Models\Menu;
use Filamentboot\Services\ActivityLogger;
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
     * 模态动作的表单组件
     *
     * 必须覆写。基类 TreePage::getFormSchema() 的实现是
     * `static::getResource()::form(Schema::make($this))->getComponents()`——它先把组件绑到一个
     * 临时的、statePath 为空的 Schema 上，Filament 5 在这次解析里就把每个字段的绝对状态路径
     * 缓存成裸字段名。之后 CreateAction/EditAction 再用 `mountedActions.0.data` 作为状态路径
     * 重新收容这批组件，缓存值不会重算——本类尤其严重：裸字段名 `title` 与
     * `Filament\Pages\BasePage::$title` 撞名，Livewire 用旧缓存路径直接
     * `data_set($this, 'title', ...)` 写这个受保护属性，服务端直接抛
     * "Cannot access protected property" 致命错误（其余字段不撞名的资源
     * 只会表现成前端 Entangle Error，见 SiteMenuItemTree 的同类注释）。
     *
     * 直接拿一批**没被容器绑过**的新组件交给动作，让动作自己的 Schema 成为它们的第一个容器。
     *
     * @return array<int, Component>
     */
    protected function getFormSchema(): array
    {
        return MenuResource::formComponents();
    }

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
