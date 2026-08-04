<?php

namespace Filamentboot\FilamentbootSite\Cms\Services;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * 相关内容推荐
 *
 * 案例 / 方案 / 产品详情页底部「你可能还想看」的取数逻辑。三类的「相关」判据各不相同
 * （案例看风格与户型、产品看分类与品牌、方案只有标签），但「不够就用最新补齐」这件事
 * 完全一样——写三遍必然漂移，所以判据由调用方传、补齐由本服务负责。
 *
 * 两趟查询，不做打分排序：
 *   第一趟 命中**任一**亲和维度的记录（OR，不是 AND）
 *   第二趟 不够 LIMIT 条时用最新内容补齐，且排除第一趟已出现的
 *
 * ⚠️ **资讯详情页不用本服务**，它的「相关阅读」是严格同分类、不补齐。那是阅读推荐，
 * 跨分类补进来的文章会误导读者；而这三类底部是浏览出口，断头路比不够精准更糟。
 * 取舍不同是有意的，理由与护栏见 SiteFrontController::newsShow() 的注释。
 *
 * 刻意不按「命中几个维度」打分：那要在 ORDER BY 里写 CASE WHEN 求和，
 * 跨 MySQL / SQLite 的写法差异会把宿主换驱动的风险引进来，
 * 而详情页底部三张卡的排序精度并不值这个代价。
 *
 * ⚠️ 查询由**调用方**用具体模型类构造并传入（已套 published() 与排序）。
 * 本服务不自己调 published()：那是模型的局部作用域，在泛型 Builder 上
 * 静态分析解析不出来，且各模型的「已发布」判据不同——SiteProduct 用
 * is_published 布尔列，其余用 published_at 时间列。
 */
class RelatedContent
{
    /**
     * 推荐条数
     *
     * 三条刚好占满一行 md:grid-cols-3，两套主题的详情页底部都是这个栅格。
     */
    public const LIMIT = 3;

    /**
     * 取某条记录的相关内容
     *
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query  已套 published() 与排序的查询（调用方用具体类构造）
     * @param  TModel  $record  当前记录，会被排除在结果外
     * @param  array<string, mixed>  $affinities  亲和维度「列名 => 期望值」，null 与空串自动忽略
     * @return Collection<int, TModel>
     */
    public function for(Builder $query, Model $record, array $affinities = []): Collection
    {
        $base = $query->whereKeyNot($record->getKey());

        // 值为空的维度直接丢掉：未归类的记录不该被「category_id 为 null」匹配到
        // 另一批同样未归类的记录，那不是相关，只是都没填
        $affinities = array_filter(
            $affinities,
            static fn (mixed $value): bool => $value !== null && $value !== '',
        );

        $tagIds = $this->tagIds($record);

        /** @var Collection<int, TModel> $records */
        $records = ($affinities === [] && $tagIds === [])
            ? $record->newCollection()
            : $this->affine($base->clone(), $affinities, $tagIds)->limit(self::LIMIT)->get();

        $missing = self::LIMIT - $records->count();

        if ($missing <= 0) {
            return $records;
        }

        /** @var Collection<int, TModel> $fill */
        $fill = $base->clone()
            ->whereKeyNot($records->modelKeys())
            ->limit($missing)
            ->get();

        /** @var Collection<int, TModel> */
        return $records->concat($fill);
    }

    /**
     * 套上亲和条件（维度之间是 OR）
     *
     * 列名来自调用方硬编码的常量，不接受请求输入。
     *
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @param  array<string, mixed>  $affinities
     * @param  array<int, int|string>  $tagIds
     * @return Builder<TModel>
     */
    protected function affine(Builder $query, array $affinities, array $tagIds): Builder
    {
        return $query->where(function (Builder $inner) use ($affinities, $tagIds): void {
            foreach ($affinities as $column => $value) {
                $inner->orWhere($column, $value);
            }

            if ($tagIds !== []) {
                // whereKey 而不是 whereIn('tag_id')：whereHas 的子查询在 site_tags 上，
                // 透视表的 tag_id 在那里是个不存在的列名
                $inner->orWhereHas('tags', fn (Builder $tags): Builder => $tags->whereKey($tagIds));
            }
        });
    }

    /**
     * 记录自身的标签 ID
     *
     * 没有 tags 关系的模型返回空数组：标签是可选能力，缺了只是少一个亲和维度。
     *
     * @return array<int, int|string>
     */
    protected function tagIds(Model $record): array
    {
        // 先用 method_exists 挡一道：对没有该关系的模型直接 loadMissing('tags')
        // 会抛 RelationNotFoundException
        if (! method_exists($record, 'tags')) {
            return [];
        }

        $tags = $record->loadMissing('tags')->getAttribute('tags');

        return $tags instanceof Collection ? $tags->modelKeys() : [];
    }
}
