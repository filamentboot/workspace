<?php

namespace Filamentboot\FilamentbootSite\Database\Seeders;

use Filamentboot\FilamentbootSite\Cms\Models\SiteMenu;
use Filamentboot\FilamentbootSite\Cms\Models\SiteMenuItem;
use Filamentboot\FilamentbootSite\Cms\Models\SitePage;
use Filamentboot\FilamentbootSite\SiteServiceProvider;
use Filamentboot\FilamentbootSite\Support\ContentTypeLabels;
use Illuminate\Database\Seeder;

/**
 * 前台导航与页脚菜单种子（3.5 期 A 段修复五）
 *
 * 注意与 SiteMenuSeeder 区分：那个写的是**后台侧边栏**（主包 Menu / menus 表），
 * 本 Seeder 写的是**前台导航**（SiteMenu / site_menus + site_menu_items）。
 *
 * ## 为什么需要它
 *
 * 各主题的 nav / footer 组件都写成 `MenuResolver::resolve(...) ?? [ ...硬编码数组 ]`，
 * 兜底是升级安全的硬要求（下游装上包还没建菜单时导航不能空）。但**兜底一旦长期
 * 生效就变成了另一个问题**：后台「导航菜单」里空空如也，前台却稳稳显示着十来个
 * 链接——运营看不见它们，也就改不了文案、改不了顺序、加不了新入口，改半天没反应。
 * 这正是「后台看得见的前台要显示得出来」的反面：前台显示的后台也得看得见。
 *
 * 所以本 Seeder 把两份兜底数组原样落库一次。落库之后兜底仍在，只是退回它真正的
 * 角色——**全新安装到运营第一次配菜单之间的过渡态**，而不是长期的实际数据源。
 *
 * ## 幂等策略：整条菜单为单位，有项就整条跳过
 *
 * 不按项 updateOrCreate：菜单项没有 slug 一类的天然业务键，label 会被运营改
 * （「资讯中心」改成「新闻动态」很正常），按 label 认就会**重复插入**一条；
 * 按 (menu_id, type, target) 认又会把运营改过的文案覆盖回去。
 *
 * 菜单是一份小而完整、由人整体编排的东西，不是增量补种的内容表——所以粒度取
 * 整条菜单：**已经有项就完全不动**，一条项都没有才铺一遍。
 *
 * ## 解析不出来的项直接不写
 *
 * route 型要在 `filamentboot-site.menu.allowed_routes` 白名单里，page 型要有对应
 * slug 的**已发布**页面。写进去也会被 MenuResolver 在渲染时丢掉（见其 branch()），
 * 那样后台列着一条前台看不到的项，又回到了本 Seeder 要修的那个毛病上。
 * 跳过时打一行 warn——静默跳过是这个仓库反复踩的坑。
 */
class SiteFrontMenuSeeder extends Seeder
{
    /**
     * 写入 main 与 footer 两条菜单
     */
    public function run(): void
    {
        $pages = SitePage::published()->pluck('id', 'slug');

        /** @var array<string, int> $pageIds */
        $pageIds = $pages->all();

        foreach ($this->menus() as $key => $menu) {
            $this->seedMenu($key, $menu['name'], $menu['items'], $pageIds);
        }
    }

    /**
     * 铺一条菜单，已有菜单项时整条跳过
     *
     * @param  list<array{type: string, label: string, target: string}>  $items
     * @param  array<string, int>  $pageIds  已发布页面的 slug => id
     */
    protected function seedMenu(string $key, string $name, array $items, array $pageIds): void
    {
        $menu = SiteMenu::query()->firstOrCreate(['key' => $key], ['name' => $name]);

        if ($menu->items()->exists()) {
            return;
        }

        $sort = 0;

        foreach ($items as $item) {
            $target = $this->resolveTarget($item, $pageIds);

            if ($target === null) {
                $this->command?->warn("菜单项「{$item['label']}」解析不出地址，已跳过（{$item['type']}: {$item['target']}）");

                continue;
            }

            SiteMenuItem::query()->create([
                'menu_id'     => $menu->getKey(),
                'parent_id'   => SiteMenuItem::defaultParentKey(),
                'type'        => $item['type'],
                'label'       => $item['label'],
                'target'      => $target,
                'sort'        => $sort += 10,
                'open_in_new' => false,
            ]);
        }
    }

    /**
     * 把定义里的 target 换成入库形态，解析不出时返回 null
     *
     * page 型定义里写的是 slug（可读、可核对），但**入库存的是页面 id**——
     * MenuResolver 按 id 查，slug 改了菜单不会断（见其 href()）。
     *
     * @param  array{type: string, label: string, target: string}  $item
     * @param  array<string, int>  $pageIds
     */
    protected function resolveTarget(array $item, array $pageIds): ?string
    {
        return match ($item['type']) {
            'page'  => isset($pageIds[$item['target']]) ? (string) $pageIds[$item['target']] : null,
            'route' => array_key_exists($item['target'], (array) config('filamentboot-site.menu.allowed_routes', []))
                ? $item['target']
                : null,
            default => $item['target'],
        };
    }

    /**
     * 两条菜单的定义，按当前主题分岔（批次 3）
     *
     * 内容类型条目与对应主题 nav / footer 组件里的兜底数组共用 ContentTypeLabels
     * （七期批次 2），播种前后前台渲染结果天然一致，不用再靠人工逐条核对；非内容
     * 类型的项（关于/联系/下载等）仍需手工保持一致。这一步是补后台的可见性，
     * 不是改版导航。日后要调整导航，改后台，不要改这里（本 Seeder 只在菜单为空时
     * 才起作用）。
     *
     * page 型的 target 写 slug，由 resolveTarget() 换成 id。
     *
     * @return array<string, array{name: string, items: list<array{type: string, label: string, target: string}>}>
     */
    protected function menus(): array
    {
        return match (SiteServiceProvider::resolveActiveTheme()) {
            'software' => $this->softwareMenus(),
            default    => $this->decorationMenus(),
        };
    }

    /**
     * decoration 主题的菜单定义
     *
     * @return array<string, array{name: string, items: list<array{type: string, label: string, target: string}>}>
     */
    protected function decorationMenus(): array
    {
        return [
            'main' => [
                'name'  => '顶部导航',
                'items' => [
                    ['type' => 'route', 'label' => ContentTypeLabels::case(), 'target' => 'site.cases.index'],
                    ['type' => 'route', 'label' => ContentTypeLabels::solution(), 'target' => 'site.solutions.index'],
                    ['type' => 'route', 'label' => ContentTypeLabels::package(), 'target' => 'site.packages.index'],
                    ['type' => 'route', 'label' => ContentTypeLabels::product(), 'target' => 'site.products.index'],
                    ['type' => 'route', 'label' => ContentTypeLabels::news(), 'target' => 'site.news.index'],
                    ['type' => 'page', 'label' => '关于我们', 'target' => 'about'],
                    ['type' => 'page', 'label' => '联系我们', 'target' => 'contact'],
                ],
            ],
            'footer' => [
                'name'  => '页脚快捷链接',
                'items' => [
                    ['type' => 'route', 'label' => ContentTypeLabels::case(), 'target' => 'site.cases.index'],
                    ['type' => 'route', 'label' => ContentTypeLabels::solution(), 'target' => 'site.solutions.index'],
                    ['type' => 'route', 'label' => ContentTypeLabels::package(), 'target' => 'site.packages.index'],
                    ['type' => 'route', 'label' => ContentTypeLabels::product(), 'target' => 'site.products.index'],
                    ['type' => 'route', 'label' => ContentTypeLabels::news(), 'target' => 'site.news.index'],
                    ['type' => 'route', 'label' => ContentTypeLabels::city(), 'target' => 'site.city.index'],
                    ['type' => 'page', 'label' => '我们的服务', 'target' => 'services'],
                    ['type' => 'page', 'label' => '关于我们', 'target' => 'about'],
                    ['type' => 'page', 'label' => '常见问题', 'target' => 'faq'],
                    ['type' => 'page', 'label' => '联系我们', 'target' => 'contact'],
                ],
            ],
        ];
    }

    /**
     * software 主题的菜单定义
     *
     * 没有「服务城市」项：软件产品没有城市页（批次 3 决定，SoftwareDemoSeeder
     * 不建 SiteCityPage）。「我们的服务」换成「快速开始」（page slug: docs）。
     *
     * 「下载与安装」（page slug: download）是六期批次 4 新增，页面本身在宿主
     * `FilamentbootWebContentSeeder` 里种（本站自己的经营内容，理由见该类注释）。
     *
     * @return array<string, array{name: string, items: list<array{type: string, label: string, target: string}>}>
     */
    protected function softwareMenus(): array
    {
        return [
            'main' => [
                'name'  => '顶部导航',
                'items' => [
                    ['type' => 'route', 'label' => ContentTypeLabels::case(), 'target' => 'site.cases.index'],
                    ['type' => 'route', 'label' => ContentTypeLabels::solution(), 'target' => 'site.solutions.index'],
                    ['type' => 'route', 'label' => ContentTypeLabels::product(), 'target' => 'site.products.index'],
                    ['type' => 'page', 'label' => '下载与安装', 'target' => 'download'],
                    ['type' => 'route', 'label' => ContentTypeLabels::package(), 'target' => 'site.packages.index'],
                    ['type' => 'route', 'label' => ContentTypeLabels::news(), 'target' => 'site.news.index'],
                    ['type' => 'page', 'label' => '关于我们', 'target' => 'about'],
                    ['type' => 'page', 'label' => '联系我们', 'target' => 'contact'],
                ],
            ],
            'footer' => [
                'name'  => '页脚快捷链接',
                'items' => [
                    ['type' => 'route', 'label' => ContentTypeLabels::case(), 'target' => 'site.cases.index'],
                    ['type' => 'route', 'label' => ContentTypeLabels::solution(), 'target' => 'site.solutions.index'],
                    ['type' => 'route', 'label' => ContentTypeLabels::product(), 'target' => 'site.products.index'],
                    ['type' => 'page', 'label' => '下载与安装', 'target' => 'download'],
                    ['type' => 'route', 'label' => ContentTypeLabels::package(), 'target' => 'site.packages.index'],
                    ['type' => 'route', 'label' => ContentTypeLabels::news(), 'target' => 'site.news.index'],
                    ['type' => 'page', 'label' => '快速开始', 'target' => 'services'],
                    ['type' => 'page', 'label' => '关于我们', 'target' => 'about'],
                    ['type' => 'page', 'label' => '常见问题', 'target' => 'faq'],
                    ['type' => 'page', 'label' => '社区', 'target' => 'community'],
                    ['type' => 'page', 'label' => '联系我们', 'target' => 'contact'],
                ],
            ],
        ];
    }
}
