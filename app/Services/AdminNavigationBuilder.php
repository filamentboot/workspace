<?php

namespace App\Services;

use App\Models\AdminUser;
use App\Models\Menu;
use Filament\Navigation\NavigationGroup;
use Filament\Navigation\NavigationItem;
use FilamentAdmin\PluginPlatform\Filament\Resources\Extensions\ExtensionResource;
use FilamentAdmin\PluginPlatform\Filament\Resources\MarketEntries\MarketEntryResource;
use Illuminate\Support\Facades\Route;
use Throwable;

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

        $systemItems = Menu::query()
            ->active()
            ->whereNull('parent_id')
            ->orderBy('sort')
            ->get()
            ->map(fn (Menu $menu): ?NavigationItem => $this->toNavigationItem($menu, $user))
            ->filter()
            ->values()
            ->all();

        if ($systemItems !== []) {
            $groups[] = NavigationGroup::make('系统管理')->items($systemItems);
        }

        $pluginMarketItems = $this->buildPluginMarketItems();

        if ($pluginMarketItems !== []) {
            $groups[] = NavigationGroup::make('插件市场')->items($pluginMarketItems);
        }

        return $groups;
    }

    /**
     * 转换单个菜单为导航项
     */
    protected function toNavigationItem(Menu $menu, AdminUser $user): ?NavigationItem
    {
        if (! $this->isVisibleTo($menu, $user)) {
            return null;
        }

        $children = Menu::query()
            ->active()
            ->where('parent_id', $menu->id)
            ->orderBy('sort')
            ->get()
            ->map(fn (Menu $child): ?NavigationItem => $this->toNavigationItem($child, $user))
            ->filter()
            ->values()
            ->all();

        $url = $this->resolveUrl($menu);

        if ($children === [] && blank($url)) {
            return null;
        }

        $item = NavigationItem::make($menu->title)
            ->icon(filled($menu->icon) ? $menu->icon : ($children !== [] ? 'heroicon-o-bars-3' : null))
            ->sort($menu->sort)
            ->visible(true);

        if ($children !== []) {
            $item->childItems($children);
        }

        if (filled($url)) {
            $item->url($url, $menu->target === 'blank');
        }

        return $item;
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

    /**
     * @return list<NavigationItem>
     */
    protected function buildPluginMarketItems(): array
    {
        return array_values(array_filter([
            $this->makePluginMarketItem(
                label: '官方市场',
                icon: 'heroicon-o-shopping-bag',
                resourceClass: MarketEntryResource::class,
                sort: 5,
            ),
            $this->makePluginMarketItem(
                label: '扩展清单',
                icon: 'heroicon-o-puzzle-piece',
                resourceClass: ExtensionResource::class,
                sort: 10,
            ),
        ]));
    }

    protected function makePluginMarketItem(
        string $label,
        string $icon,
        string $resourceClass,
        int $sort,
    ): ?NavigationItem {
        try {
            $url = $resourceClass::getUrl('index');
        } catch (Throwable) {
            return null;
        }

        return NavigationItem::make($label)
            ->icon($icon)
            ->sort($sort)
            ->url($url);
    }
}
