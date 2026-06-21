<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 创建智能方案内容表
 *
 * 含双语字段对、独立 SEO 三列、发布状态（published_at）与置顶标记。
 */
return new class extends Migration
{
    /**
     * 执行迁移
     */
    public function up(): void
    {
        if (Schema::hasTable('site_solutions')) {
            return;
        }

        Schema::create('site_solutions', function (Blueprint $table) {
            $table->id();

            // 双语标题
            $table->string('title_zh')->comment('方案标题（中文）');
            $table->string('title_en')->nullable()->comment('方案标题（英文）');

            $table->string('slug')->unique()->comment('URL 友好标识');

            // 双语描述与富文本内容
            $table->text('description_zh')->nullable()->comment('方案简介（中文）');
            $table->text('description_en')->nullable()->comment('方案简介（英文）');
            $table->longText('content_zh')->nullable()->comment('方案详情富文本（中文）');
            $table->longText('content_en')->nullable()->comment('方案详情富文本（英文）');

            // 价格区间
            $table->string('price_range')->nullable()->comment('价格区间（如 5-10万）');

            // 独立 SEO 三列（D-10-04）
            $table->string('seo_title')->nullable()->comment('SEO 标题');
            $table->string('seo_description')->nullable()->comment('SEO 描述');
            $table->string('seo_keywords')->nullable()->comment('SEO 关键词');

            // 置顶与排序
            $table->boolean('is_featured')->default(false)->index()->comment('是否置顶/精选');
            $table->unsignedInteger('sort')->default(0)->comment('排序权重');

            // 发布状态
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
        if (! Schema::hasTable('site_solutions')) {
            return;
        }

        Schema::dropIfExists('site_solutions');
    }
};
