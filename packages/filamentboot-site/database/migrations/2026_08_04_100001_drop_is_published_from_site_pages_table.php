<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 删除 site_pages.is_published 旧列
 *
 * 该列在 2026_08_03_100004 那次迁移里被 status + published_at 取代，当时**保留一个版本**
 * 供已装机的下游回滚，由 SitePage::booted() 的 saving 钩子镜像维护。原定「随包重命名
 * 一起删」，包重命名于 2026-08-03 取消后锚点没了，改挂到阶段 3 的破坏性变更批次（#27）。
 *
 * ⚠️ 只动 site_pages。site_products.is_published 是产品自己的发布列（产品没有
 * published_at 时间戳，RESEARCH Pattern 2），仍在使用中，不要连带删。
 */
return new class extends Migration
{
    /**
     * 删列
     */
    public function up(): void
    {
        if (! Schema::hasTable('site_pages') || ! Schema::hasColumn('site_pages', 'is_published')) {
            return;
        }

        Schema::table('site_pages', function (Blueprint $table) {
            $table->dropColumn('is_published');
        });
    }

    /**
     * 加回列，并按 status 重新派生取值
     *
     * 不还原成默认值就回滚，等于让旧代码读到一列全 false——那比没有这列更糟。
     */
    public function down(): void
    {
        if (! Schema::hasTable('site_pages') || Schema::hasColumn('site_pages', 'is_published')) {
            return;
        }

        Schema::table('site_pages', function (Blueprint $table) {
            $table->boolean('is_published')->default(false)->after('slug')->comment('旧列，由 status 派生');
        });

        DB::table('site_pages')
            ->where('status', 'published')
            ->update(['is_published' => true]);
    }
};
