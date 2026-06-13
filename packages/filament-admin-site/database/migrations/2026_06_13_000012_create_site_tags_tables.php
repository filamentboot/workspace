<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 创建标签表及多态中间表
 *
 * 自建多态标签实现，不引入 spatie/laravel-tags（避免其依赖 spatie/laravel-translatable，per RESEARCH Anti-Pattern）。
 * 同一迁移文件创建 site_tags 与 site_taggables 两张表（D-10-05）。
 */
return new class extends Migration
{
    /**
     * 执行迁移
     */
    public function up(): void
    {
        if (! Schema::hasTable('site_tags')) {
            Schema::create('site_tags', function (Blueprint $table) {
                $table->id();
                $table->string('name_zh')->comment('标签名称（中文）');
                $table->string('name_en')->nullable()->comment('标签名称（英文）');
                $table->string('slug')->unique()->comment('URL 友好标识');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('site_taggables')) {
            Schema::create('site_taggables', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tag_id')->comment('关联的标签 ID');
                $table->unsignedBigInteger('taggable_id')->comment('被标记模型主键');
                $table->string('taggable_type')->comment('被标记模型类名');

                $table->index(['taggable_type', 'taggable_id'], 'site_taggables_taggable_index');
                $table->index('tag_id', 'site_taggables_tag_index');
            });
        }
    }

    /**
     * 回滚迁移（反序删除，先删中间表再删标签表）
     */
    public function down(): void
    {
        if (Schema::hasTable('site_taggables')) {
            Schema::dropIfExists('site_taggables');
        }

        if (Schema::hasTable('site_tags')) {
            Schema::dropIfExists('site_tags');
        }
    }
};
