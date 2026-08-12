<?php

namespace Filamentboot\FilamentbootSite\Modules\Corporate\Home;

use Filamentboot\FilamentbootSite\Modules\Corporate\Cases\Models\SiteCase;
use Filamentboot\FilamentbootSite\Modules\Corporate\Products\Models\SiteProduct;
use Filamentboot\FilamentbootSite\Modules\Corporate\Solutions\Models\SiteSolution;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * 首页聚合区块数据提供者（#27）
 *
 * 从 SiteFrontController::home() 抽出来的理由：那是 CMS 的通用前台控制器，
 * 却硬编码了 SiteCase::featured() 这类企业站内容模块的查询。首页要展示什么
 * 是「企业站」这个模块的事，不是 CMS 核心的事。
 *
 * 换行业模块或宿主自己接一套内容时，在自己的 ServiceProvider 里
 * `$this->app->bind(HomeSectionProvider::class, MyProvider::class)` 即可整体替换，
 * 不必改控制器也不必改路由。
 */
class HomeSectionProvider
{
    /**
     * 首页各区块的数据
     *
     * 键即 home.blade.php 里的变量名，控制器直接摊进 view() 的数据数组。
     *
     * 值声明成 iterable 而非 Collection<int, Model>：Collection 的 TValue 不是协变的，
     * 写 Collection<int, Model> 会让下面各方法返回的 Collection<int, SiteCase> 被判为不兼容。
     * iterable 是协变的，同时也正好是视图对这批数据的全部要求——遍历。
     *
     * @return array<string, iterable<int, Model>>
     */
    public function sections(): array
    {
        return [
            'featuredCases'     => $this->featuredCases(),
            'featuredSolutions' => $this->featuredSolutions(),
            'featuredProducts'  => $this->featuredProducts(),
            'testimonials'      => $this->testimonials(),
            'productBrands'     => $this->productBrands(),
        ];
    }

    /**
     * 在售产品涉及的品牌名（去重、去空）
     *
     * 首页产品区下面排一行纯文字品牌名。**只出文字，不放第三方 logo** ——
     * 商标授权是另一回事，而且本项目自己的素材筛选规则就禁止第三方品牌标识。
     *
     * 这个区块要表达的是业态：本站是**渠道商 / 系统集成商**，卖各品牌的智能产品
     * 而非自有品牌。产品详情页的 brand 字段已经这么说了，但首页此前完全没有体现。
     *
     * 取值直接来自 site_products.brand，不做归一：归一属于数据层的事（写入时就该
     * 是干净的），在展示层再做一遍会掩盖脏数据。brand 为空的产品（白牌 / 店铺自营）
     * 不参与。
     *
     * @return list<string>
     */
    protected function productBrands(): array
    {
        return SiteProduct::published()
            ->whereNotNull('brand')
            ->where('brand', '!=', '')
            ->orderBy('brand')
            ->distinct()
            ->pluck('brand')
            ->all();
    }

    /**
     * 精选案例
     *
     * @return Collection<int, SiteCase>
     */
    protected function featuredCases(): Collection
    {
        return SiteCase::published()->featured()->latest('published_at')->take(6)->get();
    }

    /**
     * 精选方案
     *
     * @return Collection<int, SiteSolution>
     */
    protected function featuredSolutions(): Collection
    {
        return SiteSolution::published()->featured()->latest('published_at')->take(4)->get();
    }

    /**
     * 精选产品
     *
     * 产品没有 published_at，排序交给模型自己的 sort。
     *
     * 有封面的排前面
     * ------------
     * 首页是最该好看的一页，精选网格不该拿占位图开头。所以先按「有没有封面」分档，
     * 同档内再按 sort ——**不是过滤掉无图产品**：那会让一个刚装机、还没上传任何图的
     * 站首页产品区直接空掉，比出占位图更糟。
     *
     * 用 withCount 而不是手写子查询：`media()` 是 InteractsWithMedia 提供的
     * MorphMany，withCount 生成的 SQL 各数据库通用。
     *
     * @return Collection<int, SiteProduct>
     */
    protected function featuredProducts(): Collection
    {
        return SiteProduct::published()
            ->featured()
            ->withCount([
                'media as cover_count' => fn (Builder $query): Builder => $query
                    ->where('collection_name', 'cover'),
            ])
            ->orderByDesc('cover_count')
            ->orderBy('sort')
            ->take(6)
            ->get();
    }

    /**
     * 业主见证
     *
     * 不取 featured 案例：置顶与否是编辑对案例本身的排期判断，跟这条案例
     * 有没有配业主原话是两回事，用 featured 过滤会让大量见证白填。
     *
     * @return Collection<int, SiteCase>
     */
    protected function testimonials(): Collection
    {
        return SiteCase::published()
            ->whereNotNull('customer_name')
            ->where('customer_name', '!=', '')
            ->whereNotNull('customer_quote')
            ->where('customer_quote', '!=', '')
            ->latest('published_at')
            ->take(6)
            ->get();
    }
}
