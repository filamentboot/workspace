<?php

namespace Filamentboot\FilamentbootSite\Cms\Services;

use Filamentboot\FilamentbootSite\Models\SiteMenu;
use Filamentboot\FilamentbootSite\Models\SiteMenuItem;
use Filamentboot\FilamentbootSite\Models\SitePage;
use Filamentboot\FilamentbootSite\Support\SafeUrl;
use Illuminate\Support\Facades\Cache;

/**
 * 前台导航菜单解析（#17）
 *
 * 把 site_menus / site_menu_items 解析成导航组件能直接 foreach 的嵌套数组。
 *
 * **无数据时返回 null**，让各主题的 blade 用 `?? [ ...硬编码数组 ]` 兜底。
 * 这是升级安全的硬要求：下游装上本包但还没建菜单时，导航必须照旧显示，
 * 不能白屏也不能变成空导航栏。
 *
 * 兜底数组留在各主题的 blade 里而不是收进这里：抽到 PHP 会把两个主题的
 * 导航结构焊死，违反「双主题完全独立」。
 */
class MenuResolver
{
    /**
     * 缓存键前缀
     */
    public const CACHE_PREFIX = 'site:menu:';

    /**
     * 解析指定菜单，无可渲染项时返回 null
     *
     * 每个页面都要读导航（nav 与 footer 两条），不缓存等于全站每请求多两条查询。
     * 用 rememberForever + 模型事件失效，而不是短 TTL：菜单改动频率极低，
     * 靠 TTL 过期会让「改了菜单等几分钟才生效」变成常态投诉。
     *
     * 返回**平铺**列表而非嵌套树：两套主题的导航与页脚都是平铺结构，
     * 没有二级下拉的版式。返回嵌套结构会让后台能配出前台静默丢弃的层级，
     * 因此 SiteMenuItemTree 的 maxDepth 也定为 1——「后台配得出来的，
     * 前台一定显示得出来」这条比数据模型的表达力更重要。
     * 二级导航属阶段 4 主题契约（#28）的范围，届时连版式一起做。
     *
     * @return list<array{label: string, href: string, target: string|null}>|null
     */
    public function resolve(string $key): ?array
    {
        $items = Cache::rememberForever(self::CACHE_PREFIX.$key, fn (): array => $this->build($key));

        return $items === [] ? null : $items;
    }

    /**
     * 清除指定菜单的缓存（null 表示全部）
     */
    public static function forget(?string $key = null): void
    {
        $keys = $key !== null
            ? [$key]
            : SiteMenu::query()->pluck('key')->all();

        foreach ($keys as $menuKey) {
            Cache::forget(self::CACHE_PREFIX.$menuKey);
        }
    }

    /**
     * 从数据库组装菜单项列表
     *
     * @return list<array{label: string, href: string, target: string|null}>
     */
    protected function build(string $key): array
    {
        $menu = SiteMenu::query()->where('key', $key)->first();

        if ($menu === null) {
            return [];
        }

        $items = $menu->rootItems()->get();

        // 一次性取出全部被引用的页面，避免逐项查库（N+1）
        $pages = $this->pageUrls(
            $items->where('type', 'page')->pluck('target')->all()
        );

        $resolved = [];

        foreach ($items as $item) {
            $href = $this->href($item, $pages);

            // 解析不出地址的项整条不渲染：页面被删 / 未发布、路由不在白名单、
            // 外链被 scheme 白名单拦下都会走到这里。渲染一个无处可去的链接
            // 比少一项更糟——访客点了会以为站坏了。
            if ($href === null) {
                continue;
            }

            $resolved[] = [
                'label'  => $item->label,
                'href'   => $href,
                'target' => $item->open_in_new ? '_blank' : null,
            ];
        }

        return $resolved;
    }

    /**
     * 解析单个菜单项的地址，不可用时返回 null
     *
     * @param  array<int, string>  $pages
     */
    protected function href(SiteMenuItem $item, array $pages): ?string
    {
        $target = (string) ($item->target ?? '');

        return match ($item->type) {
            // 存 id 不存 slug：slug 改了菜单不能断，站内链接应当直接跟着走
            'page'   => $pages[(int) $target] ?? null,
            'route'  => $this->routeUrl($target),
            'url'    => SafeUrl::sanitize($target),
            'anchor' => str_starts_with($target, '#') ? $target : null,
            default  => null,
        };
    }

    /**
     * 白名单内的命名路由转 URL
     *
     * route() 对未知名称会抛异常，而导航在每个页面都渲染——
     * 一个填错的路由名会让全站白屏，所以先查白名单再 rescue 兜一层
     * （插件禁用时前台路由未注册，白名单内的名字也解析不出来）。
     */
    protected function routeUrl(string $name): ?string
    {
        $allowed = (array) config('filamentboot-site.menu.allowed_routes', []);

        if (! array_key_exists($name, $allowed)) {
            return null;
        }

        return rescue(fn (): string => route($name), null, report: false);
    }

    /**
     * 批量把页面 id 解析成 URL，只含已发布页面
     *
     * 走 published() 作用域：草稿绝不能通过导航泄露到前台（§0.3 第 3 条）。
     *
     * @param  list<mixed>  $targets  菜单项里存的 target 原值
     * @return array<int, string>
     */
    protected function pageUrls(array $targets): array
    {
        $ids = array_values(array_filter(array_map('intval', array_map('strval', $targets))));

        if ($ids === []) {
            return [];
        }

        $urls = [];

        foreach (SitePage::published()->whereKey($ids)->get(['id', 'slug']) as $page) {
            $url = rescue(fn (): string => route('site.page', $page->slug), null, report: false);

            if ($url !== null) {
                $urls[(int) $page->getKey()] = $url;
            }
        }

        return $urls;
    }
}
