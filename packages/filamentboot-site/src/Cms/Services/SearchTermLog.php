<?php

namespace Filamentboot\FilamentbootSite\Cms\Services;

use Filamentboot\FilamentbootSite\Cms\Models\SiteSearchTerm;
use Illuminate\Support\Facades\DB;

/**
 * 站内搜索词记录
 *
 * 从搜索页调用，把归一后的搜索词与结果条数累加进 site_search_terms。
 *
 * ## 三条不能违反的约束
 *
 * **一、绝不能把搜索页打成 500。** 记录是统计副作用，失败了页面照常出。
 * 所有写入包在 rescue 里，同 SearchPushObserver 的纪律。
 *
 * **二、绝不能起 session。** 公开页零 session 是全站的硬约束，一个 Set-Cookie
 * 就让整页缓存失效。这里只写库、不碰 session、不种 cookie。
 *
 * **三、不记任何身份信息。** 不存 IP、UA、referer，也不关联登录用户。
 * 要回答的问题是「大家在搜什么」，不是「谁搜了什么」——后者既不需要，
 * 也让这张表凭空变成需要保护的个人信息。
 *
 * ## 为什么用原子 upsert 而不是 firstOrCreate + increment
 *
 * 搜索是并发的，读一次再写一次会丢计数，而且两个请求同时插同一个词会撞
 * 唯一索引抛异常。`INSERT ... ON DUPLICATE KEY UPDATE hits = hits + 1`
 * 一条语句完成，数据库自己保证原子性。
 */
class SearchTermLog
{
    /**
     * 记录一次搜索
     *
     * @param  string  $term  已经过 SiteSearch::normalize() 的搜索词
     * @param  int  $resultCount  本次命中的结果条数
     */
    public function record(string $term, int $resultCount): void
    {
        if (! $this->enabled() || $term === '') {
            return;
        }

        rescue(function () use ($term, $resultCount): void {
            $now = now();

            SiteSearchTerm::query()->upsert(
                [[
                    'term'              => $term,
                    'hits'              => 1,
                    'last_result_count' => $resultCount,
                    'last_searched_at'  => $now,
                    'created_at'        => $now,
                    'updated_at'        => $now,
                ]],
                ['term'],
                [
                    // hits 必须累加，所以只有它用表达式；其余三个是本次调用的已知值，
                    // 直接当绑定参数传。**有意不用 VALUES(col)** —— 那个写法在
                    // MySQL 8.0.20 起已废弃，而且这里根本用不着它。
                    'hits'              => DB::raw($this->quote('hits').' + 1'),
                    'last_result_count' => $resultCount,
                    'last_searched_at'  => $now,
                    'updated_at'        => $now,
                ],
            );
        }, report: false);
    }

    /**
     * 是否开启记录
     */
    protected function enabled(): bool
    {
        return (bool) config('filamentboot-site.search.log_terms', true);
    }

    /**
     * 按当前数据库的标识符规则包裹列名
     *
     * 走 Grammar 而不是硬写反引号：反引号只有 MySQL 认，PostgreSQL 与 SQLite
     * 用双引号，写死一种会让这条语句在换库时静默变成语法错误。
     */
    protected function quote(string $column): string
    {
        return DB::connection()->getQueryGrammar()->wrap($column);
    }
}
