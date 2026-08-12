<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 创建城市页表
 *
 * 一个区划一个页面，挂在省级（直辖市）或地级上，URL 由所挂区划的层级决定：
 *
 *   省级 → /city/{slug}                 （北京、上海、天津、重庆）
 *   地级 → /city/{省 slug}/{市 slug}     （其余全部）
 *
 * ## 没有 slug 列，是故意的
 *
 * URL 段全部来自 `site_regions.slug`。再给页面一个自己的 slug，同一个 URL 段
 * 就有了两个来源，改一个忘了改另一个就是死链；而且 `/city/hubei/wuhan` 这个
 * 层级本身表达的就是「湖北的武汉」，页面无权把最后一段改成别的东西。
 *
 * ## `content_zh` 正常应该是 NULL
 *
 * ⚠️ **不要真的去写 300 多篇正文。** 页面主体由模板从 `profile` 渲染——
 * 城市概况表、下辖区县、同省其它城市，都是结构化数据摊开的结果。
 * `content_zh` 是**个别城市的可选覆写**：某个城市确实有值得单独写的东西时才填。
 *
 * 填了它不会替换掉概况表，是**追加**在概况之后。
 *
 * ## `profile` 的字段表在 config 里，不在这张表里
 *
 * 见 `config('filamentboot-site.city_pages.profile_fields')`。后台表单与前台模板
 * 都从那份声明生成，所以本表不认识「气候类型」「供暖方式」这些词——
 * 那是装修行业的口径，换个行业装这个包，字段表整个不一样。
 *
 * ## `region_code` 上没有外键
 *
 * 区划会随行政区划调整重新导入。挂外键的话，一次撤地设市就能把对应城市页
 * 连同正文一起级联删掉——那是**内容**，不该被参考数据的更新带走。
 * 所以只建唯一索引，孤儿记录靠导入命令的报告发现。
 */
return new class extends Migration
{
    /**
     * 执行迁移
     */
    public function up(): void
    {
        if (Schema::hasTable('site_city_pages')) {
            return;
        }

        Schema::create('site_city_pages', function (Blueprint $table) {
            $table->id();

            // 一个区划最多一个页面。不加外键，理由见类注释
            $table->string('region_code', 6)->unique()->comment('对应 site_regions.code');

            $table->string('title_zh')->comment('页面标题，如「武汉全屋智能装修」');
            // 叫 description_zh 而不是 summary_zh：六类内容里五类都用这个名字，
            // SEO 回退链（buildSeo）与 llms.txt 都按它取摘要。多起一个名字就要
            // 在那两处各加一个分支，而字段命名本来就已经有 excerpt_zh 这个例外了
            $table->text('description_zh')->nullable()->comment('一句话简介，列表卡片与 meta description 用');
            $table->longText('content_zh')->nullable()->comment('可选正文覆写，正常为 NULL（见类注释）');

            // 城市概况。键为 config 里声明的字段 key，值为字符串
            $table->json('profile')->nullable()->comment('城市概况键值对，字段表见 config');

            // 独立 SEO 三列（D-10-04）
            $table->string('seo_title')->nullable()->comment('SEO 标题');
            $table->string('seo_description')->nullable()->comment('SEO 描述');
            $table->string('seo_keywords')->nullable()->comment('SEO 关键词');

            $table->unsignedInteger('sort')->default(0)->comment('排序权重，同值时按区划代码排');

            // 发布状态。三期先只发第一批，其余留 NULL，扩量走 artisan 不重新部署
            $table->timestamp('published_at')->nullable()->index()->comment('发布时间（null 为草稿）');

            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * 回滚迁移
     */
    public function down(): void
    {
        Schema::dropIfExists('site_city_pages');
    }
};
