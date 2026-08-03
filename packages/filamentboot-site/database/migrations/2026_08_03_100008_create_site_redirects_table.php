<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 创建 URL 重定向表（#11，供 #18 301 重定向使用）
 *
 * 页面 slug 变更后旧 URL 必须能跳到新地址，否则已被搜索引擎收录的链接
 * 和外部引用一起变成 404，收录权重直接归零。
 *
 * hits 记录命中次数，用于判断哪些旧链接还活着、哪些可以清理。
 */
return new class extends Migration
{
    /**
     * 执行迁移
     */
    public function up(): void
    {
        if (Schema::hasTable('site_redirects')) {
            return;
        }

        Schema::create('site_redirects', function (Blueprint $table) {
            $table->id();
            $table->string('from_path', 500)->unique()->comment('源路径（不含域名，如 /old-about）');
            $table->string('to_path', 500)->comment('目标路径或完整 URL');
            $table->unsignedSmallInteger('status_code')->default(301)->comment('跳转状态码（301 永久 / 302 临时）');
            $table->unsignedBigInteger('hits')->default(0)->comment('命中次数');
            $table->timestamps();
        });
    }

    /**
     * 回滚迁移
     */
    public function down(): void
    {
        Schema::dropIfExists('site_redirects');
    }
};
