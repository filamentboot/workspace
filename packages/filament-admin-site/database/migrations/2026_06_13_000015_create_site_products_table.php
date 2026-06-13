<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 创建智能产品内容表
 *
 * 含双语字段对、独立 SEO 三列、价格、品牌、分类关联与 is_published 发布布尔列。
 * 产品无 published_at 时间戳，使用布尔 is_published 控制发布状态（RESEARCH Pattern 2）。
 */
return new class extends Migration
{
    /**
     * 执行迁移
     */
    public function up(): void
    {
        if (Schema::hasTable('site_products')) {
            return;
        }

        Schema::create('site_products', function (Blueprint $table) {
            $table->id();

            // 双语标题
            $table->string('title_zh')->comment('产品名称（中文）');
            $table->string('title_en')->nullable()->comment('产品名称（英文）');

            $table->string('slug')->unique()->comment('URL 友好标识');

            // 双语描述
            $table->text('description_zh')->nullable()->comment('产品描述（中文）');
            $table->text('description_en')->nullable()->comment('产品描述（英文）');

            // 价格与品牌
            $table->decimal('price', 10, 2)->nullable()->comment('产品售价');
            $table->string('brand')->nullable()->comment('品牌');

            // 分类关联
            $table->unsignedBigInteger('category_id')->nullable()->index()->comment('关联分类 ID');

            // 独立 SEO 三列（D-10-04）
            $table->string('seo_title')->nullable()->comment('SEO 标题');
            $table->string('seo_description')->nullable()->comment('SEO 描述');
            $table->string('seo_keywords')->nullable()->comment('SEO 关键词');

            // 置顶与排序
            $table->boolean('is_featured')->default(false)->index()->comment('是否置顶/精选');
            $table->unsignedInteger('sort')->default(0)->comment('排序权重');

            // 发布状态（产品用布尔值，per RESEARCH Pattern 2）
            $table->boolean('is_published')->default(true)->index()->comment('是否已发布');

            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * 回滚迁移
     */
    public function down(): void
    {
        if (! Schema::hasTable('site_products')) {
            return;
        }

        Schema::dropIfExists('site_products');
    }
};
