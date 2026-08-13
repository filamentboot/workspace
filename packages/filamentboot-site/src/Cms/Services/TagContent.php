<?php

namespace Filamentboot\FilamentbootSite\Cms\Services;

use Filamentboot\FilamentbootSite\Models\SiteTag;
use Filamentboot\FilamentbootSite\Modules\Corporate\Cases\Models\SiteCase;
use Filamentboot\FilamentbootSite\Modules\Corporate\Packages\Models\SitePackage;
use Filamentboot\FilamentbootSite\Modules\Corporate\Products\Models\SiteProduct;
use Filamentboot\FilamentbootSite\Modules\Corporate\Solutions\Models\SiteSolution;
use Filamentboot\FilamentbootSite\Modules\News\Models\NewsArticle;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

/**
 * 标签聚合取数（/tags/{slug}）
 *
 * 五类内容各查一次、按类型分组返回。**返回结构与 Cms\Services\SiteSearch::search()
 * 完全一致**，两套主题的标签页视图因此可以照搬搜索结果页的版式——聚合页要的就是
 * 「标题 + 一句摘要 + 链接」这个密度，不需要封面卡片那一套。
 *
 * 不做跨表 UNION，理由同 SiteSearch：五张表的列不同名，收益只是「能真分页」，
 * 而标签这个维度的量级本来就小（一个标签下几条到几十条）。
 *
 * ## 它解决的是内链问题，不是检索问题
 *
 * 加这个页面之前，站上 58 条标签关联只在资讯详情渲染成灰色 `<span>`——不是链接，
 * 也没有落点。内容之间除了「同分类」「同户型」这类硬维度外没有横向通路，
 * 深处的内容离首页超过 3 跳。标签是唯一一条跨内容类型的通路：
 * 一个「节能环保」能把案例、方案、套餐、资讯串起来，别的维度都做不到。
 *
 * 宿主要换取数口径就 bind 掉这个类，控制器与路由都不用动。
 */
class TagContent
{
    /**
     * 每类内容返回的最大条数
     *
     * 超出部分不静默丢弃：分组带 hasMore 标记，视图会给出「看全部」的出口。
     */
    public const PER_GROUP = 24;

    /**
     * 取某个标签下的已发布内容，按类型分组
     *
     * 分组为空的类型直接不出现（而不是出一个空标题），空数组表示该标签下
     * 一条已发布内容都没有——调用方据此决定 404。
     *
     * @return list<array{key: string, label: string, indexUrl: string, hasMore: bool, hits: list<array{title: string, excerpt: string, url: string}>}>
     */
    public function groups(SiteTag $tag): array
    {
        $groups = [
            $this->cases($tag),
            $this->solutions($tag),
            $this->packages($tag),
            $this->products($tag),
            $this->news($tag),
        ];

        return array_values(array_filter($groups, static fn (?array $group): bool => $group !== null));
    }

    /**
     * 该标签下是否有任何已发布内容
     *
     * 与 groups() 同一个判据的便宜版本：`||` 会短路，多数标签只花一次查询，
     * 而 groups() 恒定五次并且把记录全取回来。站点地图靠它剔掉空标签——
     * 空标签页是 404，写进站点地图等于主动交一批死链给搜索引擎。
     *
     * ⚠️ **加内容类型时这里和 groups() 要一起改**，漏一边就会出现
     * 「站点地图里有、点进去 404」或反过来「页面能开却不在站点地图里」。
     */
    public function hasContent(SiteTag $tag): bool
    {
        return $tag->cases()->published()->exists()
            || $tag->solutions()->published()->exists()
            || $tag->packages()->published()->exists()
            || $tag->products()->published()->exists()
            || $tag->news()->published()->exists();
    }

    /**
     * 分组内命中条数合计
     *
     * @param  list<array{hits: list<array{title: string, excerpt: string, url: string}>, ...}>  $groups
     */
    public function countHits(array $groups): int
    {
        return array_sum(array_map(
            static fn (array $group): int => count($group['hits']),
            $groups
        ));
    }

    /**
     * 装修案例
     *
     * @return array{key: string, label: string, indexUrl: string, hasMore: bool, hits: list<array{title: string, excerpt: string, url: string}>}|null
     */
    protected function cases(SiteTag $tag): ?array
    {
        /** @var Collection<int, SiteCase> $records */
        $records = $this->take($tag->cases()->published()->latest('published_at'));

        return $this->group(
            'case',
            '装修案例',
            route('site.cases.index'),
            $records,
            fn (SiteCase $record): array => [
                'title'   => $record->title_zh,
                'excerpt' => $this->excerpt((string) ($record->description_zh ?: $record->content_zh)),
                'url'     => route('site.cases.show', ['slug' => $record->slug]),
            ]
        );
    }

    /**
     * 智能方案
     *
     * @return array{key: string, label: string, indexUrl: string, hasMore: bool, hits: list<array{title: string, excerpt: string, url: string}>}|null
     */
    protected function solutions(SiteTag $tag): ?array
    {
        /** @var Collection<int, SiteSolution> $records */
        $records = $this->take($tag->solutions()->published()->latest('published_at'));

        return $this->group(
            'solution',
            '智能方案',
            route('site.solutions.index'),
            $records,
            fn (SiteSolution $record): array => [
                'title'   => $record->title_zh,
                'excerpt' => $this->excerpt((string) ($record->description_zh ?: $record->content_zh)),
                'url'     => route('site.solutions.show', ['slug' => $record->slug]),
            ]
        );
    }

    /**
     * 全屋套餐
     *
     * @return array{key: string, label: string, indexUrl: string, hasMore: bool, hits: list<array{title: string, excerpt: string, url: string}>}|null
     */
    protected function packages(SiteTag $tag): ?array
    {
        /** @var Collection<int, SitePackage> $records */
        $records = $this->take($tag->packages()->published()->orderedForCompare());

        return $this->group(
            'package',
            '全屋套餐',
            route('site.packages.index'),
            $records,
            fn (SitePackage $record): array => [
                'title'   => $record->title_zh,
                'excerpt' => $this->excerpt((string) ($record->description_zh ?: $record->content_zh)),
                'url'     => route('site.packages.show', ['slug' => $record->slug]),
            ]
        );
    }

    /**
     * 智能产品
     *
     * @return array{key: string, label: string, indexUrl: string, hasMore: bool, hits: list<array{title: string, excerpt: string, url: string}>}|null
     */
    protected function products(SiteTag $tag): ?array
    {
        /** @var Collection<int, SiteProduct> $records */
        $records = $this->take($tag->products()->published()->orderBy('sort')->latest('id'));

        return $this->group(
            'product',
            '智能产品',
            route('site.products.index'),
            $records,
            fn (SiteProduct $record): array => [
                'title'   => $record->title_zh,
                'excerpt' => $this->excerpt((string) ($record->description_zh ?: $record->content_zh)),
                'url'     => route('site.products.show', ['slug' => $record->slug]),
            ]
        );
    }

    /**
     * 资讯文章
     *
     * @return array{key: string, label: string, indexUrl: string, hasMore: bool, hits: list<array{title: string, excerpt: string, url: string}>}|null
     */
    protected function news(SiteTag $tag): ?array
    {
        /** @var Collection<int, NewsArticle> $records */
        $records = $this->take($tag->news()->published()->latest('published_at'));

        return $this->group(
            'news',
            '资讯',
            route('site.news.index'),
            $records,
            fn (NewsArticle $record): array => [
                'title'   => $record->title_zh,
                'excerpt' => $this->excerpt((string) ($record->description_zh ?: $record->content_zh)),
                'url'     => route('site.news.show', ['slug' => $record->slug]),
            ]
        );
    }

    /**
     * 多取一条落库
     *
     * 多取的那条只用来判断「还有更多」，比再发一次 count() 便宜（同 SiteSearch::group）。
     *
     * @param  MorphToMany<covariant Model, SiteTag>  $relation
     * @return Collection<int, Model>
     */
    protected function take(MorphToMany $relation): Collection
    {
        /** @var Collection<int, Model> $records */
        $records = $relation->limit(self::PER_GROUP + 1)->get();

        return $records;
    }

    /**
     * 把一类内容整理成分组
     *
     * 泛型化是必须的：Eloquent Collection 的 TModel 不协变，
     * 声明成 Collection<int, Model> 收不下 Collection<int, SiteCase>。
     *
     * @template TModel of Model
     *
     * @param  Collection<int, TModel>  $records
     * @param  callable(TModel): array{title: string, excerpt: string, url: string}  $mapper
     * @return array{key: string, label: string, indexUrl: string, hasMore: bool, hits: list<array{title: string, excerpt: string, url: string}>}|null
     */
    protected function group(string $key, string $label, string $indexUrl, Collection $records, callable $mapper): ?array
    {
        if ($records->isEmpty()) {
            return null;
        }

        return [
            'key'      => $key,
            'label'    => $label,
            'indexUrl' => $indexUrl,
            'hasMore'  => $records->count() > self::PER_GROUP,
            'hits'     => $records->take(self::PER_GROUP)->map($mapper)->values()->all(),
        ];
    }

    /**
     * 富文本压成一句纯文本摘要
     *
     * 与 SiteSearch::snippet() 有意分开：那个要按关键词位置取窗口，这里没有关键词，
     * 从头截即可。合并只会让两边都多一个用不上的参数。
     */
    protected function excerpt(string $text, int $length = 120): string
    {
        $text = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = trim((string) preg_replace('/\s+/u', ' ', $text));

        if ($text === '') {
            return '';
        }

        return mb_substr($text, 0, $length).(mb_strlen($text) > $length ? '…' : '');
    }
}
