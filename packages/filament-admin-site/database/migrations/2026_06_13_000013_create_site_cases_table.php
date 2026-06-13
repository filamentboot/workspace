<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 创建装修案例内容表
 *
 * 包含双语字段对（title_zh/title_en 等）、独立 SEO 三列、
 * 装修风格与户型枚举列、图集 JSON 列、发布状态与置顶标记（SITE-01 数据契约）。
 * gallery 使用 nullable() 而非 default('[]')，避免 MySQL 8.0 JSON 默认值兼容问题。
 */
return new class extends Migration
{
    /**
     * 执行迁移
     */
    public function up(): void
    {
        if (Schema::hasTable('site_cases')) {
            return;
        }

        Schema::create('site_cases', function (Blueprint $table) {
            $table->id();

            // 双语标题
            $table->string('title_zh')->comment('案例标题（中文）');
            $table->string('title_en')->nullable()->comment('案例标题（英文）');

            $table->string('slug')->unique()->comment('URL 友好标识');

            // 装修属性（存枚举 value，字符串列）
            $table->string('style')->nullable()->comment('装修风格（CaseStyle enum value）');
            $table->string('house_type')->nullable()->comment('户型（HouseType enum value）');
            $table->string('area')->nullable()->comment('面积（如 120㎡）');
            $table->string('budget_range')->nullable()->comment('预算区间（如 20-30万）');
            $table->text('smart_features')->nullable()->comment('智能配置说明');

            // 双语描述与富文本内容
            $table->text('description_zh')->nullable()->comment('案例简介（中文）');
            $table->text('description_en')->nullable()->comment('案例简介（英文）');
            $table->longText('content_zh')->nullable()->comment('案例详情富文本（中文）');
            $table->longText('content_en')->nullable()->comment('案例详情富文本（英文）');

            // 独立 SEO 三列（D-10-04）
            $table->string('seo_title')->nullable()->comment('SEO 标题');
            $table->string('seo_description')->nullable()->comment('SEO 描述');
            $table->string('seo_keywords')->nullable()->comment('SEO 关键词');

            // 分类关联
            $table->unsignedBigInteger('category_id')->nullable()->index()->comment('关联分类 ID');

            // 图集 JSON 列（Media Library 图集或手动输入图片 URL 数组）
            $table->json('gallery')->nullable()->comment('图集（URL 数组，避免 MySQL 8.0 JSON 默认值问题）');

            // 置顶与排序
            $table->boolean('is_featured')->default(false)->index()->comment('是否置顶/精选');
            $table->unsignedInteger('sort')->default(0)->comment('排序权重');

            // 发布状态（published_at 为 null 表示草稿）
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
        if (! Schema::hasTable('site_cases')) {
            return;
        }

        Schema::dropIfExists('site_cases');
    }
};
