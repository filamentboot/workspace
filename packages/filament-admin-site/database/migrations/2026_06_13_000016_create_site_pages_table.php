<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 创建静态页面内容表
 *
 * 含双语字段对、独立 SEO 三列、is_published 发布布尔列。
 * 静态页面无 published_at，无媒体库（无封面图）。
 */
return new class extends Migration
{
    /**
     * 执行迁移
     */
    public function up(): void
    {
        if (Schema::hasTable('site_pages')) {
            return;
        }

        Schema::create('site_pages', function (Blueprint $table) {
            $table->id();

            // 双语标题
            $table->string('title_zh')->comment('页面标题（中文）');
            $table->string('title_en')->nullable()->comment('页面标题（英文）');

            $table->string('slug')->unique()->comment('URL 友好标识（如 about、contact）');

            // 双语富文本内容
            $table->longText('content_zh')->nullable()->comment('页面内容富文本（中文）');
            $table->longText('content_en')->nullable()->comment('页面内容富文本（英文）');

            // 独立 SEO 三列（D-10-04）
            $table->string('seo_title')->nullable()->comment('SEO 标题');
            $table->string('seo_description')->nullable()->comment('SEO 描述');
            $table->string('seo_keywords')->nullable()->comment('SEO 关键词');

            // 排序
            $table->unsignedInteger('sort')->default(0)->comment('排序权重');

            // 发布状态（静态页面用布尔值）
            $table->boolean('is_published')->default(false)->index()->comment('是否已发布');

            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * 回滚迁移
     */
    public function down(): void
    {
        if (! Schema::hasTable('site_pages')) {
            return;
        }

        Schema::dropIfExists('site_pages');
    }
};
