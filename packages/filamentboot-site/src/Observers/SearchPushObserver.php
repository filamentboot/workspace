<?php

namespace Filamentboot\FilamentbootSite\Observers;

use Filamentboot\FilamentbootSite\Cms\Models\SitePage;
use Filamentboot\FilamentbootSite\Jobs\PushUrlsToBaidu;
use Filamentboot\FilamentbootSite\Modules\Corporate\Cases\Models\SiteCase;
use Filamentboot\FilamentbootSite\Modules\Corporate\Products\Models\SiteProduct;
use Filamentboot\FilamentbootSite\Modules\Corporate\Solutions\Models\SiteSolution;
use Filamentboot\FilamentbootSite\Modules\News\Models\NewsArticle;
use Filamentboot\FilamentbootSite\Services\BaiduPushService;
use Illuminate\Database\Eloquent\Model;

/**
 * 内容发布后主动推送搜索引擎（B4）
 *
 * 只在**发布状态发生变化且当前对公众可见**时推，不是每次保存都推：
 * 百度普通站每天配额 3000 条，改一个错别字就烧一条不划算。
 *
 * 「是否可见」一律回查各模型自己的 published() 作用域，不在这里复刻一遍
 * 发布判据——四个模型的判据本就不同（SitePage 看 status、产品看 is_published、
 * 其余看 published_at），复刻等于给自己埋两套会漂移的规则。
 */
class SearchPushObserver
{
    /**
     * 模型 → 前台详情路由名
     *
     * @var array<class-string<Model>, string>
     */
    protected const ROUTES = [
        SiteCase::class     => 'site.cases.show',
        SiteSolution::class => 'site.solutions.show',
        SiteProduct::class  => 'site.products.show',
        SitePage::class     => 'site.page',
        NewsArticle::class  => 'site.news.show',
    ];

    /**
     * 可能承载发布状态的列
     *
     * 取并集而非按模型分支：某个模型没有其中某列时 wasChanged() 只会返回 false，
     * 不会报错，而新增内容类型时这里不必跟着改。
     *
     * @var list<string>
     */
    protected const PUBLISH_COLUMNS = ['status', 'published_at', 'is_published'];

    /**
     * 模型保存后
     */
    public function saved(Model $model): void
    {
        if (! $model->wasChanged(self::PUBLISH_COLUMNS)) {
            return;
        }

        $this->pushIfVisible($model);
    }

    /**
     * 当前可见则推送其详情页 URL
     */
    protected function pushIfVisible(Model $model): void
    {
        // 未配置 token 时连队列都不占
        if (! app(BaiduPushService::class)->isEnabled()) {
            return;
        }

        $url = static::publicUrl($model);

        if ($url === null) {
            return;
        }

        // 通知类副作用一律不能把内容保存打成 500（同 A2 的纪律）
        try {
            PushUrlsToBaidu::dispatch([$url]);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /**
     * 取模型当前对公众可见的详情页 URL，不可见或无对应路由时返回 null
     */
    public static function publicUrl(Model $model): ?string
    {
        $routeName = self::ROUTES[$model::class] ?? null;

        if ($routeName === null || ! self::isVisible($model)) {
            return null;
        }

        // 插件禁用时前台路由未注册，route() 会抛 —— 此时本就不该推
        return rescue(
            fn (): string => route($routeName, $model->getAttribute('slug')),
            null,
            report: false,
        );
    }

    /**
     * 该记录当前是否对公众可见
     *
     * 回查各模型自己的 published() 作用域：草稿、定时未到、已归档一律不推。
     *
     * 逐个 case 写死具体类而不是 $model->newQuery()->published()：published()
     * 是模型作用域，经 Builder<Model> 调用时静态分析看不见它。写成具体类既
     * 保住类型，又仍然复用各模型自己的发布判据（四类内容判据本就不同）。
     */
    protected static function isVisible(Model $model): bool
    {
        $key = $model->getKey();

        return match ($model::class) {
            SiteCase::class     => SiteCase::published()->whereKey($key)->exists(),
            SiteSolution::class => SiteSolution::published()->whereKey($key)->exists(),
            SiteProduct::class  => SiteProduct::published()->whereKey($key)->exists(),
            SitePage::class     => SitePage::published()->whereKey($key)->exists(),
            NewsArticle::class  => NewsArticle::published()->whereKey($key)->exists(),
            default             => false,
        };
    }

    /**
     * 全部已发布内容的详情页 URL（供 filamentboot-site:push-baidu 回推）
     *
     * 直接 pluck 全量 slug 而不分块：一条 slug 几十字节，上万条也就几百 KB，
     * 而百度普通站每天配额才 3000 条——内容量真到需要分块的规模时，
     * 瓶颈早已是配额而不是内存。
     *
     * @return list<string>
     */
    public static function allPublishedUrls(): array
    {
        $sources = [
            'site.cases.show'     => SiteCase::published()->pluck('slug'),
            'site.solutions.show' => SiteSolution::published()->pluck('slug'),
            'site.products.show'  => SiteProduct::published()->pluck('slug'),
            'site.page'           => SitePage::published()->pluck('slug'),
            'site.news.show'      => NewsArticle::published()->pluck('slug'),
        ];

        $urls = [];

        foreach ($sources as $routeName => $slugs) {
            foreach ($slugs as $slug) {
                $url = rescue(
                    fn (): string => route($routeName, $slug),
                    null,
                    report: false,
                );

                if ($url !== null) {
                    $urls[] = $url;
                }
            }
        }

        return array_values(array_unique($urls));
    }
}
