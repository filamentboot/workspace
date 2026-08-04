<?php

namespace Filamentboot\FilamentbootSite\Cms\Services;

use Filamentboot\FilamentbootSite\Cms\Models\SitePage;
use Filamentboot\FilamentbootSite\Modules\Corporate\Cases\Models\SiteCase;
use Filamentboot\FilamentbootSite\Modules\Corporate\Products\Models\SiteProduct;
use Filamentboot\FilamentbootSite\Modules\Corporate\Solutions\Models\SiteSolution;
use Filamentboot\FilamentbootSite\Modules\News\Models\NewsArticle;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * 站内搜索（跨模块）
 *
 * 前台 /search?q= 的取数。五类内容各查一次、各自限流，按类型分组返回——
 * 不做跨表 UNION：五张表的列不同名，UNION 要先投影成统一列集，写法与各驱动的
 * 类型推断纠缠，收益只是「能真分页」，而企业站的搜索每类给五条已经够用。
 *
 * 宿主要换内容源就 bind 掉这个类（同 Modules\Corporate\Home\HomeSectionProvider
 * 的做法），控制器与路由都不用动。
 *
 * ## 匹配方式与它的代价
 *
 * `LIKE '%词%'`。前置通配符用不上索引，等于每类内容一次全表扫描——企业站几百上千条
 * 完全无感，上万条就要换 FULLTEXT（中文还需 `WITH PARSER ngram`），那是一次迁移 +
 * 索引维护，不在本次范围。因此也**没有相关度排序**：排序按各类型自己的自然顺序
 * （最新 / sort），要按相关度排必须先有全文索引的评分。
 *
 * ⚠️ **区块正文搜不到。** site_pages.blocks 是 JSON 列，Eloquent 存入时非 ASCII 字符
 * 会被 json_encode 转成 Unicode 转义序列——「中文」两个字落库后是六个 ASCII 字符
 * 打头的转义写法（u4e2d / u6587 那种），不是「中文」本身。
 * 所以 `LIKE '%中文%'` 永远不可能命中它。页面只按 title_zh / content_zh /
 * seo_description 匹配——纯区块搭出来的页面就只能靠标题被搜到。
 * 要覆盖区块正文，得加一列由观察器维护的 search_text（渲染后的纯文本），
 * 那是独立的一次改动，不是这里加个列名能解决的。
 */
class SiteSearch
{
    /**
     * 每类内容返回的最大条数
     */
    public const PER_GROUP = 5;

    /**
     * 关键词最大长度
     *
     * 截断而不是拒绝：超长关键词多半是误粘贴，截断后照样给结果比报错友好。
     */
    public const MAX_TERM_LENGTH = 50;

    /**
     * LIKE 转义字符
     *
     * 刻意不用默认的反斜杠：MySQL 的字符串字面量自己也处理反斜杠，
     * `ESCAPE '\'` 在 MySQL 里是未闭合字符串，而写成 `ESCAPE '\\'` 在
     * SQLite / Postgres 里又变成两个反斜杠。换一个不会被任何一方二次处理的
     * 字符，三种驱动行为一致。
     */
    protected const LIKE_ESCAPE = '!';

    /**
     * 执行搜索，按内容类型分组
     *
     * 关键词为空时返回空数组且**不查库**：空搜索是一次全表扫描换零信息。
     *
     * @return list<array{key: string, label: string, hasMore: bool, hits: list<array{title: string, excerpt: string, url: string}>}>
     */
    public function search(string $term): array
    {
        $term = $this->normalize($term);

        if ($term === '') {
            return [];
        }

        $groups = [
            $this->pages($term),
            $this->cases($term),
            $this->solutions($term),
            $this->products($term),
            $this->news($term),
        ];

        return array_values(array_filter($groups, static fn (?array $group): bool => $group !== null));
    }

    /**
     * 归一化关键词
     *
     * 折叠空白 + 去控制字符 + 截断。公开是因为控制器要把同一份归一结果回显到
     * 输入框与标题里——回显原始输入而按归一后的词去查，会出现「显示的词和搜的词不一样」。
     */
    public function normalize(string $term): string
    {
        $term = (string) preg_replace('/[\x00-\x1F\x7F]/u', '', $term);
        $term = (string) preg_replace('/\s+/u', ' ', $term);

        return mb_substr(trim($term), 0, self::MAX_TERM_LENGTH);
    }

    /**
     * 静态页面
     *
     * @return array{key: string, label: string, hasMore: bool, hits: list<array{title: string, excerpt: string, url: string}>}|null
     */
    protected function pages(string $term): ?array
    {
        $records = $this->constrain(
            SitePage::published()->orderBy('sort')->orderBy('id'),
            $term,
            ['title_zh', 'content_zh', 'seo_description']
        )->limit(self::PER_GROUP + 1)->get();

        return $this->group('page', '页面', $records, fn (SitePage $record): array => [
            'title'   => $record->title_zh,
            'excerpt' => $this->snippet((string) ($record->seo_description ?: $record->content_zh), $term),
            'url'     => route('site.page', ['slug' => $record->slug]),
        ]);
    }

    /**
     * 装修案例
     *
     * @return array{key: string, label: string, hasMore: bool, hits: list<array{title: string, excerpt: string, url: string}>}|null
     */
    protected function cases(string $term): ?array
    {
        $records = $this->constrain(
            SiteCase::published()->latest('published_at'),
            $term,
            ['title_zh', 'description_zh', 'content_zh']
        )->limit(self::PER_GROUP + 1)->get();

        return $this->group('case', '装修案例', $records, fn (SiteCase $record): array => [
            'title'   => $record->title_zh,
            'excerpt' => $this->snippet((string) ($record->description_zh ?: $record->content_zh), $term),
            'url'     => route('site.cases.show', ['slug' => $record->slug]),
        ]);
    }

    /**
     * 智能方案
     *
     * @return array{key: string, label: string, hasMore: bool, hits: list<array{title: string, excerpt: string, url: string}>}|null
     */
    protected function solutions(string $term): ?array
    {
        $records = $this->constrain(
            SiteSolution::published()->latest('published_at'),
            $term,
            ['title_zh', 'description_zh', 'content_zh']
        )->limit(self::PER_GROUP + 1)->get();

        return $this->group('solution', '智能方案', $records, fn (SiteSolution $record): array => [
            'title'   => $record->title_zh,
            'excerpt' => $this->snippet((string) ($record->description_zh ?: $record->content_zh), $term),
            'url'     => route('site.solutions.show', ['slug' => $record->slug]),
        ]);
    }

    /**
     * 智能产品
     *
     * @return array{key: string, label: string, hasMore: bool, hits: list<array{title: string, excerpt: string, url: string}>}|null
     */
    protected function products(string $term): ?array
    {
        $records = $this->constrain(
            SiteProduct::published()->orderBy('sort')->orderBy('id'),
            $term,
            ['title_zh', 'description_zh', 'content_zh', 'brand']
        )->limit(self::PER_GROUP + 1)->get();

        return $this->group('product', '智能产品', $records, fn (SiteProduct $record): array => [
            'title'   => $record->title_zh,
            'excerpt' => $this->snippet((string) ($record->description_zh ?: $record->content_zh), $term),
            'url'     => route('site.products.show', ['slug' => $record->slug]),
        ]);
    }

    /**
     * 资讯文章
     *
     * @return array{key: string, label: string, hasMore: bool, hits: list<array{title: string, excerpt: string, url: string}>}|null
     */
    protected function news(string $term): ?array
    {
        $records = $this->constrain(
            NewsArticle::published()->latest('published_at'),
            $term,
            ['title_zh', 'excerpt_zh', 'content_zh']
        )->limit(self::PER_GROUP + 1)->get();

        return $this->group('news', '资讯', $records, fn (NewsArticle $record): array => [
            'title'   => $record->title_zh,
            'excerpt' => $this->snippet((string) ($record->excerpt_zh ?: $record->content_zh), $term),
            'url'     => route('site.news.show', ['slug' => $record->slug]),
        ]);
    }

    /**
     * 给查询套上「任一列 LIKE 关键词」
     *
     * 列名来自本类硬编码的常量，不接受请求输入；仍走 grammar 的 wrap()
     * 而不是直接拼进 SQL，换驱动时标识符引用方式跟着走。
     *
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @param  list<string>  $columns
     * @return Builder<TModel>
     */
    protected function constrain(Builder $query, string $term, array $columns): Builder
    {
        $pattern = '%'.$this->escapeLike($term).'%';

        return $query->where(function (Builder $inner) use ($columns, $pattern): void {
            $grammar = $inner->getQuery()->getGrammar();

            foreach ($columns as $column) {
                $inner->orWhereRaw(
                    $grammar->wrap($column)." LIKE ? ESCAPE '".self::LIKE_ESCAPE."'",
                    [$pattern]
                );
            }
        });
    }

    /**
     * 转义关键词里的 LIKE 通配符
     *
     * 不转义的话「50%」会退化成「50 后面任意内容」，而「a_b」会匹配 a 任意字符 b。
     * 不是安全问题（值仍是绑定参数），是结果对不对的问题。
     */
    protected function escapeLike(string $term): string
    {
        return str_replace(
            [self::LIKE_ESCAPE, '%', '_'],
            [self::LIKE_ESCAPE.self::LIKE_ESCAPE, self::LIKE_ESCAPE.'%', self::LIKE_ESCAPE.'_'],
            $term
        );
    }

    /**
     * 把一类内容的查询结果整理成分组
     *
     * 多取一条用来判断「还有更多」，比再发一次 count() 便宜。
     *
     * 泛型化是必须的：Eloquent Collection 的 TModel 不协变，
     * 声明成 Collection<int, Model> 收不下 Collection<int, SitePage>。
     *
     * @template TModel of Model
     *
     * @param  Collection<int, TModel>  $records
     * @param  callable(TModel): array{title: string, excerpt: string, url: string}  $mapper
     * @return array{key: string, label: string, hasMore: bool, hits: list<array{title: string, excerpt: string, url: string}>}|null
     */
    protected function group(string $key, string $label, Collection $records, callable $mapper): ?array
    {
        if ($records->isEmpty()) {
            return null;
        }

        $hasMore = $records->count() > self::PER_GROUP;

        $hits = $records->take(self::PER_GROUP)
            ->map($mapper)
            ->values()
            ->all();

        return [
            'key'     => $key,
            'label'   => $label,
            'hasMore' => $hasMore,
            'hits'    => $hits,
        ];
    }

    /**
     * 截取命中位置附近的一段纯文本作摘要
     *
     * 不做关键词高亮：高亮要输出 <mark> 标签，就得让视图用 {!! !!}，
     * 而这段文本里混着作者写的富文本内容——为了一个视觉效果开一个 HTML 注入口不值。
     */
    protected function snippet(string $text, string $term, int $length = 120): string
    {
        $text = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = trim((string) preg_replace('/\s+/u', ' ', $text));

        if ($text === '') {
            return '';
        }

        $position = mb_stripos($text, $term);

        // 命中在开头附近就从头截，不必为了「居中」在前面加个没意义的省略号
        $start = ($position === false || $position <= 30) ? 0 : $position - 30;

        $snippet = mb_substr($text, $start, $length);

        return ($start > 0 ? '…' : '')
            .$snippet
            .(mb_strlen($text) > $start + $length ? '…' : '');
    }
}
