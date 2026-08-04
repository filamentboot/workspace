<?php

namespace Filamentboot\FilamentbootSite\Filament\Resources\SiteMenuItemResource\Pages;

use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filamentboot\FilamentbootSite\Filament\Resources\SiteMenuItemResource;
use Filamentboot\FilamentbootSite\Filament\Resources\SiteMenuResource;
use Filamentboot\FilamentbootSite\Models\SiteMenu;
use Filamentboot\FilamentbootSite\Models\SiteMenuItem;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use SolutionForest\FilamentTree\Actions\EditAction;
use SolutionForest\FilamentTree\Resources\Pages\TreePage;

/**
 * 菜单项树形管理页（#17）
 *
 * 一个树页服务所有菜单，靠 ?menu={key} 决定编哪一条——入口是 SiteMenuResource
 * 列表页的「管理菜单项」动作。
 *
 * 为什么不做成 SiteMenuResource 的 /{record}/items 记录页：TreePage 继承的是
 * Filament 的普通 Page，没有记录绑定；硬塞 InteractsWithRecord 之后
 * getModel() / getFormSchema() / 三个 configure*Action() 全都得改指向另一个
 * 模型和另一份表单，越权改动库里的约定。查询串方案只需覆写 getTreeQuery()。
 *
 * 最大层级设为 1（只有根层，树在这里的作用是拖拽排序）：两套主题的导航与页脚
 * 都是平铺结构，没有二级下拉的版式，MenuResolver 也只返回平铺列表。
 * 允许后台配出二级，等于允许它配出前台静默丢弃的内容。
 * 二级导航属阶段 4 主题契约（#28）的范围，届时连版式一起放开。
 */
class SiteMenuItemTree extends TreePage
{
    protected static string $resource = SiteMenuItemResource::class;

    protected static int $maxDepth = 1;

    /**
     * 当前编辑的菜单 key（来自查询串，Livewire 属性同步进 URL）
     */
    #[Url(as: 'menu', history: true)]
    public ?string $menu = null;

    /**
     * 缓存解析出的菜单，避免每次 getTreeQuery() 都查库
     */
    protected ?SiteMenu $resolvedMenu = null;

    /**
     * 页面标题带上菜单名，避免同一个树页在两条菜单间切换后分不清
     */
    public function getTitle(): string
    {
        $menu = $this->currentMenu();

        return $menu !== null ? '菜单项 · '.$menu->name : '菜单项';
    }

    /**
     * 未指定或指定了不存在的菜单时，回落到第一条菜单
     *
     * 直接 abort(404) 会让「刚装上包、菜单表为空」变成一个错误页；
     * 回落 + 空树更接近实际期望（进来就能建第一项）。
     */
    protected function currentMenu(): ?SiteMenu
    {
        if ($this->resolvedMenu !== null) {
            return $this->resolvedMenu;
        }

        $query = SiteMenu::query();

        $menu = is_string($this->menu) && $this->menu !== ''
            ? (clone $query)->where('key', $this->menu)->first()
            : null;

        return $this->resolvedMenu = $menu ?? $query->orderBy('id')->first();
    }

    /**
     * 当前菜单 id，无菜单时返回 0（查询将命中空集）
     */
    protected function currentMenuId(): int
    {
        return (int) ($this->currentMenu()?->getKey() ?? 0);
    }

    /**
     * 只取当前菜单下的菜单项
     *
     * 不过滤的话两条菜单的项会混在同一棵树上，拖拽还会把 footer 的项
     * 挪到 main 的节点下面。
     *
     * @return Builder<SiteMenuItem>
     */
    protected function getTreeQuery(): Builder
    {
        return SiteMenuItem::query()->where('menu_id', $this->currentMenuId());
    }

    /**
     * 默认全部展开：两层结构没有折叠的必要
     */
    public function getNodeCollapsedState(?Model $record = null): bool
    {
        return false;
    }

    /**
     * 覆写基类的 getRecords()，refreshTree 时先清对象级缓存再重查
     *
     * 同主包 MenuTree 踩过的坑：基类有 $this->records 缓存，拖拽排序后
     * dispatch refreshTree 时缓存未清，Livewire 重渲染仍用旧数据，
     * 树看起来"回弹"到拖拽前的顺序。
     *
     * @return Collection<int, Model>|null
     */
    #[On('refreshTree')]
    public function getRecords(): ?Collection
    {
        $this->records = null;

        return parent::getRecords();
    }

    /**
     * 顶部动作：返回菜单列表 + 在两条菜单间直接切换
     *
     * @return array<int, mixed>
     */
    protected function getHeaderActions(): array
    {
        $actions = [
            Action::make('backToMenus')
                ->label('返回导航列表')
                ->icon('heroicon-o-arrow-left')
                ->color('gray')
                ->url(fn (): string => SiteMenuResource::getUrl()),
        ];

        foreach (SiteMenu::query()->orderBy('id')->get() as $menu) {
            if ($menu->getKey() === $this->currentMenu()?->getKey()) {
                continue;
            }

            $actions[] = Action::make('switchTo'.$menu->getKey())
                ->label('切换到「'.$menu->name.'」')
                ->icon('heroicon-o-arrows-right-left')
                ->color('gray')
                ->url(fn (): string => static::getUrl(['menu' => $menu->key]));
        }

        $actions[] = $this->getCreateAction();

        return $actions;
    }

    /**
     * 新建时注入 menu_id 并把四个 target_* 字段收敛成 target
     */
    protected function afterConfiguredCreateAction(CreateAction $action): CreateAction
    {
        return $action->mutateDataUsing(fn (array $data): array => [
            ...SiteMenuItemResource::collapseTarget($data),
            'menu_id' => $this->currentMenuId(),
        ]);
    }

    /**
     * 编辑时：回填展开 target，保存时再收敛回去
     */
    protected function afterConfiguredEditAction(EditAction $action): EditAction
    {
        return $action
            ->mutateRecordDataUsing(fn (array $data): array => SiteMenuItemResource::expandTarget($data))
            ->mutateDataUsing(fn (array $data): array => SiteMenuItemResource::collapseTarget($data));
    }

    /**
     * 树上启用删除动作：菜单项没有软删除，删了就是删了，但导航项本就是可弃的配置
     */
    protected function hasDeleteAction(): bool
    {
        return true;
    }
}
