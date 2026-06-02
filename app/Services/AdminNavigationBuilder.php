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

        $url = $this->resolveUrl($menu);

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

        $path = trim(parse_url($url, PHP_URL_PATH) ?? '', '/');

        return NavigationItem::make($label)
            ->icon($icon)
            ->sort($sort)
            ->url($url)
            ->isActiveWhen(fn (): bool => request()->is($path) || request()->is($path.'/*'));
    }
}
