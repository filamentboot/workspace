<?php

namespace FilamentAdmin\Services;

use Filament\Facades\Filament;
use Filament\Navigation\NavigationGroup;
use Filament\Navigation\NavigationItem;
use FilamentAdmin\Models\AdminUser;
use FilamentAdmin\Models\Menu;
use Illuminate\Support\Facades\Route;

/**
 * 后台动态导航构建器
 */
class AdminNavigationBuilder
{
    /**
     * 构建当前管理员可见导航
     *
     * @return array<NavigationGroup>
     */
    public function build(?AdminUser $user): array
    {
        if (! $user) {
            return [];
        }

        $groups = [];

        $topMenus = Menu::query()
            ->active()
            ->where('type', 'menu')
            ->where('parent_id', 0)
            ->orderBy('sort')
            ->get();

        foreach ($topMenus as $topMenu) {
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

            if ($items === []) {
                continue;
            }

            $groups[] = NavigationGroup::make($topMenu->title)->items($items);
        }

        return $groups;
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
            $parts  = explode('.', $menu->route_name);
            array_pop($parts);
            $prefix = implode('.', $parts);

            return request()->routeIs($prefix.'.*')
                || request()->routeIs($menu->route_name);
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
