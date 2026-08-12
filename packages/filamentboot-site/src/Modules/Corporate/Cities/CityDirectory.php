<?php

namespace Filamentboot\FilamentbootSite\Modules\Corporate\Cities;

use Filamentboot\FilamentbootSite\Modules\Corporate\Cities\Models\SiteCityPage;
use Filamentboot\FilamentbootSite\Modules\Corporate\Cities\Models\SiteRegion;
use Illuminate\Database\Eloquent\Collection;

/**
 * 城市页目录服务
 *
 * 把「哪些城市有页、按省怎么分组、某个省下有哪些城市」这几件事收在一处：
 * 城市总索引、省页、城市页、站点地图、llms.txt 都要问同样的问题，
 * 各自写一遍查询迟早漂移（尤其是**直辖市那条分支**——它的页面挂在省级上，
 * 少判一次就会在某个列表里凭空消失）。
 *
 * 全部方法只返回**已发布**的页面。草稿不进任何列表、任何索引。
 */
class CityDirectory
{
    /**
     * 按省分组的全部已发布城市页
     *
     * 城市总索引页用它。一次性把页面与区划都取回来在 PHP 里分组，而不是
     * 「先查省、再逐省查市」——后者是 30 多次查询，而全站城市页撑死三百多条，
     * 一次取完再分组更省事。
     *
     * `ownPage` 是直辖市自己的页面（挂在省级区划上），`pages` 是下辖地级市的页面。
     * 两者都空的省不出现在结果里——省页会 404，列一个死链没有意义。
     *
     * @return list<array{region: SiteRegion, ownPage: SiteCityPage|null, pages: list<SiteCityPage>}>
     */
    public function groupedByProvince(): array
    {
        $pages = $this->publishedPages();

        /** @var array<string, SiteCityPage> $ownByProvince */
        $ownByProvince = [];
        /** @var array<string, list<SiteCityPage>> $childrenByProvince */
        $childrenByProvince = [];

        foreach ($pages as $page) {
            $region = $page->region;

            if ($region === null) {
                continue;
            }

            if ($region->level === SiteRegion::LEVEL_PROVINCE) {
                $ownByProvince[$region->code] = $page;

                continue;
            }

            $childrenByProvince[$region->parent_code][] = $page;
        }

        $codes = array_values(array_unique([
            ...array_keys($ownByProvince),
            ...array_keys($childrenByProvince),
        ]));

        if ($codes === []) {
            return [];
        }

        $provinces = SiteRegion::query()
            ->level(SiteRegion::LEVEL_PROVINCE)
            ->whereIn('code', $codes)
            ->ordered()
            ->get();

        $grouped = [];

        foreach ($provinces as $province) {
            $grouped[] = [
                'region'  => $province,
                'ownPage' => $ownByProvince[$province->code] ?? null,
                'pages'   => $childrenByProvince[$province->code] ?? [],
            ];
        }

        return $grouped;
    }

    /**
     * 某个省下的已发布城市页，按区划代码序
     *
     * @return list<SiteCityPage>
     */
    public function citiesIn(SiteRegion $province): array
    {
        return $this->orderedPages(
            SiteCityPage::published()
                ->join('site_regions', 'site_regions.code', '=', 'site_city_pages.region_code')
                ->where('site_regions.parent_code', $province->code)
                ->where('site_regions.level', SiteRegion::LEVEL_CITY)
                ->select('site_city_pages.*')
                ->with('region')
                ->orderBy('site_city_pages.sort')
                ->orderBy('site_regions.code')
                ->get()
        );
    }

    /**
     * 同省的其它已发布城市页（排除自己）
     *
     * 城市页底部的横向出口。**不做「最新几条」兜底**：同省没有别的城市页时
     * 整块不渲染，跨省推荐对着「我要在本地找人装修」这个意图是噪音。
     *
     * @return list<SiteCityPage>
     */
    public function siblingsOf(SiteCityPage $page, int $limit = 12): array
    {
        $region = $page->region;

        if ($region === null || $region->level !== SiteRegion::LEVEL_CITY) {
            return [];
        }

        $siblings = SiteCityPage::published()
            ->join('site_regions', 'site_regions.code', '=', 'site_city_pages.region_code')
            ->where('site_regions.parent_code', $region->parent_code)
            ->where('site_regions.level', SiteRegion::LEVEL_CITY)
            ->whereKeyNot($page->getKey())
            ->select('site_city_pages.*')
            ->with('region')
            ->orderBy('site_city_pages.sort')
            ->orderBy('site_regions.code')
            ->limit($limit)
            ->get();

        return $this->orderedPages($siblings);
    }

    /**
     * 某个区划下辖的县级单位
     *
     * 城市页上「下辖区县」那一块的数据源，也是**全站差异度最高的一段文字**：
     * 武汉 13 个区、黄石 6 个，名字完全不同，一个字都不用编。
     * 三期批次 5 的「任意两页正文差异 >10%」很大程度靠它撑。
     *
     * 对直辖市同样成立：导入时占位容器（市辖区 / 县）已被丢掉、下辖区县
     * 直接挂在省级上，所以这里不需要为直辖市分支。
     *
     * @return Collection<int, SiteRegion>
     */
    public function countiesOf(SiteRegion $region): Collection
    {
        return $region->children()->where('level', SiteRegion::LEVEL_COUNTY)->get();
    }

    /**
     * 全部已发布城市页（区划已预加载）
     *
     * 站点地图与 llms.txt 直接用它。**不设上限**：城市页总量的天花板是行政区划
     * 数量（三百多），不像内容那样会无限增长。
     *
     * @return Collection<int, SiteCityPage>
     */
    public function publishedPages(): Collection
    {
        return SiteCityPage::published()
            ->with(['region', 'region.parent'])
            ->orderBy('sort')
            ->orderBy('region_code')
            ->get();
    }

    /**
     * 把查询结果转成 list，顺带丢掉区划断链的记录
     *
     * 区划重新导入后可能有页面对不上任何区划（撤地设市之类）。那种页面
     * `url()` 会抛异常，放进列表就是一个 500——**列表里少一条，好过整页崩掉**。
     * 这类孤儿由导入命令的报告负责暴露，不在渲染路径上处理。
     *
     * @param  Collection<int, SiteCityPage>  $pages
     * @return list<SiteCityPage>
     */
    protected function orderedPages(Collection $pages): array
    {
        return array_values(
            $pages->filter(static fn (SiteCityPage $page): bool => $page->region !== null)->all()
        );
    }
}
