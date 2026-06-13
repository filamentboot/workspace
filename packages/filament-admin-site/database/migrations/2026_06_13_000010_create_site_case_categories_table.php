<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 创建装修案例分类表
 *
 * 首版扁平结构，parent_id 预留字段但不使用层级（D-10-05/Open Question 2 决议）。
 */
return new class extends Migration
{
    /**
     * 执行迁移
     */
    public function up(): void
    {
        if (Schema::hasTable('site_case_categories')) {
            return;
        }

        Schema::create('site_case_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name_zh')->comment('分类名称（中文）');
            $table->string('name_en')->nullable()->comment('分类名称（英文）');
            $table->string('slug')->unique()->comment('URL 友好标识');
            $table->unsignedBigInteger('parent_id')->nullable()->comment('父级分类 ID（首版扁平，预留字段）');
            $table->unsignedInteger('sort')->default(0)->comment('排序权重');
            $table->timestamps();
        });
    }

    /**
     * 回滚迁移
     */
    public function down(): void
    {
        if (! Schema::hasTable('site_case_categories')) {
            return;
        }

        Schema::dropIfExists('site_case_categories');
    }
};
