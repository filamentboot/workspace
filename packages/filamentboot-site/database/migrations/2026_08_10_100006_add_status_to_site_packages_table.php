<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 全屋套餐补 status 列，覆盖发布状态机（批次 1.5a，四期功能清单第 1 档 #1）
 *
 * 回填规则见 2026_08_10_100002_add_status_to_site_cases_table 的完整说明。
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('site_packages') || Schema::hasColumn('site_packages', 'status')) {
            return;
        }

        Schema::table('site_packages', function (Blueprint $table) {
            $table->string('status', 20)->default('draft')->index()
                ->comment('发布状态（PageStatus enum value）')->after('published_at');
        });

        DB::table('site_packages')
            ->whereNotNull('published_at')
            ->update(['status' => 'published']);
    }

    public function down(): void
    {
        if (! Schema::hasTable('site_packages') || ! Schema::hasColumn('site_packages', 'status')) {
            return;
        }

        Schema::table('site_packages', function (Blueprint $table) {
            $table->dropColumn('status');
        });
    }
};
