<?php

namespace Filamentboot\FilamentbootSite\Modules\Corporate\Home;

use Filamentboot\FilamentbootSite\Modules\Corporate\Cases\Models\SiteCase;
use Filamentboot\FilamentbootSite\Modules\Corporate\Products\Models\SiteProduct;
use Filamentboot\FilamentbootSite\Modules\Corporate\Solutions\Models\SiteSolution;
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
        ];
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
     * 产品没有 published_at，排序交给模型自己的默认顺序。
     *
     * @return Collection<int, SiteProduct>
     */
    protected function featuredProducts(): Collection
    {
        return SiteProduct::published()->featured()->take(6)->get();
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
