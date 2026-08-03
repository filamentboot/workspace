<?php

namespace Filamentboot\Services;

use Filament\Facades\Filament;
use Filament\Navigation\NavigationGroup;
use Filament\Navigation\NavigationItem;
use Filamentboot\Models\AdminUser;
use Filamentboot\Models\Menu;
use Illuminate\Support\Facades\Route;

/**
 * 后台动态导航构建器
 */
class AdminNavigationBuilder
{
    /**
     * 构建当前管理员可见导航
     *
     * 根节点判定逻辑：
     * - 正常情况下，根节点 parent_id = Menu::defaultParentKey()（整数 0，filament-tree 约定）
     * - 防御性兼容：若库中存在 parent_id IS NULL 的历史行也纳入根集合
     *
     * 顶级组处理逻辑：
     * - 有可见子菜单时：渲染为 NavigationGroup + 子项
     * - 无可见子菜单、但自身可解析为可点击项（有 url/route_name 且权限放行）时：
     *   包进无标题 NavigationGroup 渲染为顶级可点击项（如仪表盘）
     * - 无可见子菜单且自身无法解析为可点击项时：跳过（不产生空组）
     *
     * 全部元素统一返回 NavigationGroup：NavigationBuilder::groups() 只接受
     * NavigationGroup，混入 NavigationItem 会在 getNavigation() 里调用不存在的
     * NavigationItem::getItems() 而抛错。
     *
     * @return array<NavigationGroup>
     */
    public function build(?AdminUser $user): array
    {
        if (! $user) {
            return [];
        }

        $result = [];

        // 根节点查询：兼容 parent_id = defaultParentKey()(=0) 与历史 parent_id IS NULL 的行
        $topMenus = Menu::query()
            ->active()
            ->where('type', 'menu')
            ->where(function ($query): void {
                $query->where('parent_id', Menu::defaultParentKey())
                    ->orWhereNull('parent_id');
            })
            ->orderBy('sort')
            ->get();

        foreach ($topMenus as $topMenu) {
            // 查询该顶级菜单下的可见子菜单
            $items = Menu::query()
                ->active()
                ->where('type', 'menu')
                ->where('parent_id', $topMenu->id)
                ->orderBy('sort')
                ->get()
                ->map(fn (Menu $child): ?NavigationItem => $this->toNavigationItem($child, $user))
                ->filter()
                ->values()
                ->all();

            if ($items !== []) {
                // 有可见子菜单：渲染为导航分组，分组行挂上菜单自身的图标，
                // 侧边栏折叠时 Filament 才能用一个图标代表整组并弹出悬浮子菜单
                $groupIcon = filled($topMenu->icon) ? $topMenu->icon : null;

                if ($groupIcon !== null) {
                    // Filament 约定分组与子项只能一方带图标，两者都有会直接抛异常
                    // （折叠态侧边栏下它会静默清掉子项图标，但不能依赖面板一定开启折叠）
                    foreach ($items as $item) {
                        $item->icon(null);
                    }
                }

                $result[] = NavigationGroup::make($topMenu->title)
                    ->icon($groupIcon)
                    ->items($items);

                continue;
            }

            // 无可见子菜单：尝试将顶级组自身解析为可点击导航项
            $topItem = $this->toNavigationItem($topMenu, $user);

            if ($topItem !== null) {
                // 顶级菜单自身可点击（有 url/route_name 且权限放行）：包进无标题分组，
                // 既保留它在 sort 序列中的位置，又不破坏 groups() 的类型契约
                $result[] = NavigationGroup::make()->items([$topItem]);
            }
            // 既无子项也无可点击 url：跳过，不产生空组
        }

        return $result;
    }

    /**
     * 转换单个菜单为导航项
     *
     * 对于 route_name 为 null 且 url 也为空的叶子节点，尝试通过面板已注册的
     * Resource 或 Page 按菜单标题匹配补全导航项（修复 SC-1 导航过滤问题）。
     */
    protected function toNavigationItem(Menu $menu, AdminUser $user): ?NavigationItem
    {
        if (! $this->isVisibleTo($menu, $user)) {
            return null;
        }

        $url = $this->resolveUrl($menu);

        // route_name 为 null 且 url 也为空时，尝试通过面板已注册的 Resource/Page 补全
        if (blank($url) && blank($menu->route_name) && blank($menu->url)) {
            return $this->resolveFromPanel($menu);
        }

        if (blank($url)) {
            return null;
        }

        return NavigationItem::make($menu->title)
            ->icon(filled($menu->icon) ? $menu->icon : null)
            ->sort($menu->sort)
            ->url($url, $menu->target === 'blank')
            ->isActiveWhen(fn (): bool => $this->isItemActive($menu, $url))
            ->visible(true);
    }

    /**
     * 通过面板已注册的 Resource 或 Page 按菜单标题补全导航项
     *
     * 遍历当前面板的 Resource 和 Page，按导航标签（navigationLabel）匹配菜单标题，
     * 命中后使用其原生路由生成 NavigationItem。匹配不到时返回 null 维持原过滤行为。
     */
    protected function resolveFromPanel(Menu $menu): ?NavigationItem
    {
        try {
            $panel = Filament::getCurrentPanel();

            if (! $panel) {
                return null;
            }

            // 遍历面板已注册的 Resource，按导航标签匹配菜单标题
            foreach ($panel->getResources() as $resourceClass) {
                $label = $resourceClass::getNavigationLabel();

                if ($label === $menu->title) {
                    // 获取 Resource index 路由名
                    $routeName = "filament.{$panel->getId()}.resources.{$resourceClass::getSlug()}.index";

                    if (Route::has($routeName)) {
                        $url = route($routeName);

                        return NavigationItem::make($menu->title)
                            ->icon(filled($menu->icon) ? $menu->icon : $resourceClass::getNavigationIcon())
                            ->sort($menu->sort)
                            ->url($url)
                            ->isActiveWhen(fn (): bool => request()->routeIs("filament.{$panel->getId()}.resources.{$resourceClass::getSlug()}.*"))
                            ->visible(true);
                    }
                }
            }

            // 遍历面板已注册的 Page，按导航标签匹配菜单标题
            foreach ($panel->getPages() as $pageClass) {
                $label = $pageClass::getNavigationLabel();

                if ($label === $menu->title) {
                    $slug = $pageClass::getSlug();

                    if (filled($slug)) {
                        $routeName = "filament.{$panel->getId()}.pages.{$slug}";

                        if (Route::has($routeName)) {
                            $url = route($routeName);

                            return NavigationItem::make($menu->title)
                                ->icon(filled($menu->icon) ? $menu->icon : $pageClass::getNavigationIcon())
                                ->sort($menu->sort)
                                ->url($url)
                                ->isActiveWhen(fn (): bool => request()->routeIs($routeName))
                                ->visible(true);
                        }
                    }
                }
            }
        } catch (\Throwable) {
            // 面板未初始化或获取失败时，静默返回 null
        }

        return null;
    }

    /**
     * 判断菜单项是否处于激活状态
     */
    protected function isItemActive(Menu $menu, string $url): bool
    {
        if (filled($menu->route_name) && Route::has($menu->route_name)) {
            // Resource 路由形如 filament.{panel}.resources.{slug}.{action}，
            // 去掉末段后按 {slug}.* 匹配，使 create / edit / view 等子页面同样高亮父菜单。
            if (str_contains($menu->route_name, '.resources.')) {
                $parts = explode('.', $menu->route_name);
                array_pop($parts);
                $prefix = implode('.', $parts);

                return request()->routeIs($prefix.'.*');
            }

            // Page 路由（filament.{panel}.pages.{slug}）本身即完整标识，只能精确匹配：
            // 若同样去掉末段做前缀匹配，pages.* 会让仪表盘与所有设置页互相串台高亮。
            return request()->routeIs($menu->route_name);
        }

        $path = trim(parse_url($url, PHP_URL_PATH) ?? '', '/');

        return request()->is($path) || request()->is($path.'/*');
    }

    /**
     * 判断菜单是否对当前管理员可见
     */
    protected function isVisibleTo(Menu $menu, AdminUser $user): bool
    {
        return blank($menu->permission_name) || $user->can($menu->permission_name);
    }

    /**
     * 解析菜单跳转地址
     */
    protected function resolveUrl(Menu $menu): ?string
    {
        if (filled($menu->route_name)) {
            return Route::has($menu->route_name) ? route($menu->route_name) : null;
        }

        return filled($menu->url) ? $menu->url : null;
    }
}
