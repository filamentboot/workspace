<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 创建资讯文章表
 *
 * 官网此前只有案例/方案/产品三类「存量」内容，条数固定，sitemap 永远那几十条，
 * 搜索引擎抓取频次上不去，也没有素材做外链和分发。资讯流是营销站持续产出
 * 长尾落地页的 SEO 引擎。
 *
 * 发布态用 published_at 时间戳而非 is_published 布尔（与 site_cases 一致）：
 * 归档页需要按年月分组，布尔字段无法支撑。
 */
return new class extends Migration
{
    /**
     * 执行迁移
     */
    public function up(): void
    {
        if (Schema::hasTable('site_news_articles')) {
            return;
        }

        Schema::create('site_news_articles', function (Blueprint $table) {
            $table->id();

            // 标题与标识
            $table->string('title_zh')->comment('文章标题（中文）');
            $table->string('title_en')->nullable()->comment('文章标题（英文）');
            $table->string('slug')->unique()->comment('URL 友好标识');

            // 正文
            $table->text('excerpt_zh')->nullable()->comment('文章摘要（中文），列表页与 OG 描述兜底');
            $table->text('excerpt_en')->nullable()->comment('文章摘要（英文）');
            $table->longText('content_zh')->nullable()->comment('文章正文富文本（中文）');
            $table->longText('content_en')->nullable()->comment('文章正文富文本（英文）');

            // 关联
            $table->unsignedBigInteger('category_id')->nullable()->index()->comment('关联分类 ID');

            // SEO
            $table->string('seo_title')->nullable()->comment('SEO 标题');
            $table->string('seo_description')->nullable()->comment('SEO 描述');
            $table->string('seo_keywords')->nullable()->comment('SEO 关键词');

            // 展示控制
            $table->boolean('is_featured')->default(false)->index()->comment('是否置顶/精选');
            $table->unsignedInteger('sort')->default(0)->comment('排序权重');

            // 发布态：null 为草稿，归档页按年月分组依赖此列
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
        if (! Schema::hasTable('site_news_articles')) {
            return;
        }

        Schema::dropIfExists('site_news_articles');
    }
};
