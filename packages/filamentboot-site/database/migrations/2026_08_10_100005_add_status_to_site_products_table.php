<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 智能产品补 status 列，覆盖发布状态机（批次 1.5a，四期功能清单第 1 档 #1）
 *
 * 回填规则见 2026_08_10_100002_add_status_to_site_cases_table 的完整说明。
 * 必须排在 2026_08_10_100001_convert_site_products_to_published_at 之后
 * （published_at 列由那次迁移创建）。
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('site_products') || Schema::hasColumn('site_products', 'status')) {
            return;
        }

        Schema::table('site_products', function (Blueprint $table) {
            $table->string('status', 20)->default('draft')->index()
                ->comment('发布状态（PageStatus enum value）')->after('published_at');
        });

        DB::table('site_products')
            ->whereNotNull('published_at')
            ->update(['status' => 'published']);
    }

    public function down(): void
    {
        if (! Schema::hasTable('site_products') || ! Schema::hasColumn('site_products', 'status')) {
            return;
        }

        Schema::table('site_products', function (Blueprint $table) {
            $table->dropColumn('status');
        });
    }
};
