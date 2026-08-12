<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 装修案例补 status 列，覆盖发布状态机（批次 1.5a，四期功能清单第 1 档 #1）
 *
 * PageStatus 枚举与 SitePage 零耦合，直接复用其 5 态（draft/review/scheduled/
 * published/archived）。回填只做二值判断：published_at 非空即 published，
 * 否则 draft——不区分历史上的"未来时间"记录（那类记录本就被 scopePublished()
 * 的时间比较挡住，回填成 published 不影响前台可见性，只是后台徽标暂时
 * 不精确，可后续手工改成 scheduled）。
 *
 * 与 site_pages 用同一套列定义（string(20) + default draft + index）。
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('site_cases') || Schema::hasColumn('site_cases', 'status')) {
            return;
        }

        Schema::table('site_cases', function (Blueprint $table) {
            $table->string('status', 20)->default('draft')->index()
                ->comment('发布状态（PageStatus enum value）')->after('published_at');
        });

        DB::table('site_cases')
            ->whereNotNull('published_at')
            ->update(['status' => 'published']);
    }

    public function down(): void
    {
        if (! Schema::hasTable('site_cases') || ! Schema::hasColumn('site_cases', 'status')) {
            return;
        }

        Schema::table('site_cases', function (Blueprint $table) {
            $table->dropColumn('status');
        });
    }
};
