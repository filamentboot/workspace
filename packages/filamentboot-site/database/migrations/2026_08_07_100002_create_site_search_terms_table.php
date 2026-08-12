<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 站内搜索词统计表
 *
 * 站内搜索早就有了，但**用户搜了什么从来没记下来**。这是全站最真实的关键词
 * 需求来源——不是关键词工具估出来的搜索量，是访客在你站上亲手打进去的词。
 *
 * 表设计成**按词聚合**而不是逐次流水：
 *
 * - 运营要看的是「哪些词被搜得多」「哪些词搜不出东西」，不是谁在几点搜了什么
 * - 不存 IP、不存 UA、不关联任何身份，天然规避个人信息问题
 * - 一个词一行，几万次搜索也就几千行，后台列表直接排序不用聚合查询
 *
 * `last_result_count` 是这张表最有价值的一列：**结果数为 0 的词就是内容缺口**，
 * 直接指导下一批写什么。没有它，这张表只是个热词榜。
 */
return new class extends Migration
{
    /**
     * 执行迁移
     */
    public function up(): void
    {
        Schema::create('site_search_terms', function (Blueprint $table): void {
            $table->id();

            // 长度与 SiteSearch::MAX_TERM_LENGTH 对齐；唯一索引承担 upsert 的冲突键。
            // utf8mb4 下 50 字符的唯一索引是 200 字节，远在 InnoDB 单列 3072 字节限内。
            $table->string('term', 50)->unique();

            $table->unsignedInteger('hits')->default(1)->comment('累计搜索次数');
            $table->unsignedInteger('last_result_count')->default(0)->comment('最近一次的结果条数，0 即内容缺口');
            $table->timestamp('last_searched_at')->nullable();
            $table->timestamps();

            // 后台默认按热度倒序；「零结果」筛选走 last_result_count
            $table->index(['hits']);
            $table->index(['last_result_count']);
        });
    }

    /**
     * 回滚迁移
     */
    public function down(): void
    {
        Schema::dropIfExists('site_search_terms');
    }
};
