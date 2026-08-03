<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 页面数据模型 CMS 化演进（#11）
 *
 * 从「富文本静态页」升级为「模板 + 区块 + 状态机」的 CMS 页面：
 * - template     页面模板标识
 * - blocks       区块 payload（JSON，区块契约见 src/Cms/Blocks）
 * - status       发布状态（PageStatus 枚举值），取代 is_published 布尔列
 * - published_at 发布时间，配合 status=scheduled 实现定时发布
 * - seo_og_image 页面级 Open Graph 图
 *
 * is_published 旧列**保留一个版本**不删（已确认的决策）：已装机的下游若回滚
 * 代码仍需该列存在。计划在阶段 4 随包重命名的破坏性变更一起清理，
 * 期间由 SitePage 的 saving 钩子把它镜像为 status 的派生值，避免数据失真。
 *
 * 逐列 hasColumn 守卫，可重复执行。
 */
return new class extends Migration
{
    /**
     * 执行迁移
     */
    public function up(): void
    {
        if (! Schema::hasTable('site_pages')) {
            return;
        }

        Schema::table('site_pages', function (Blueprint $table) {
            if (! Schema::hasColumn('site_pages', 'template')) {
                $table->string('template', 100)->default('default')->after('slug')
                    ->comment('页面模板标识');
            }

            if (! Schema::hasColumn('site_pages', 'blocks')) {
                $table->json('blocks')->nullable()->after('content_en')
                    ->comment('区块 payload（键为已注册的区块 key）');
            }

            if (! Schema::hasColumn('site_pages', 'status')) {
                $table->string('status', 20)->default('draft')->index()->after('sort')
                    ->comment('发布状态（PageStatus enum value）');
            }

            if (! Schema::hasColumn('site_pages', 'published_at')) {
                $table->timestamp('published_at')->nullable()->index()->after('status')
                    ->comment('发布时间，status=scheduled 时用于定时发布');
            }

            if (! Schema::hasColumn('site_pages', 'seo_og_image')) {
                $table->string('seo_og_image', 1024)->nullable()->after('seo_keywords')
                    ->comment('页面级 Open Graph 图');
            }
        });

        $this->backfillStatus();
    }

    /**
     * 把旧的 is_published 布尔值转换为 status + published_at
     *
     * 已发布页面的 published_at 取 updated_at：这是能拿到的最接近发布时刻的
     * 时间戳，且必须落在过去，否则 scopePublished() 的定时过滤会把它判为未到期。
     */
    private function backfillStatus(): void
    {
        if (! Schema::hasColumn('site_pages', 'is_published')) {
            return;
        }

        DB::table('site_pages')
            ->where('is_published', true)
            ->update([
                'status'       => 'published',
                'published_at' => DB::raw('COALESCE(published_at, updated_at, created_at)'),
            ]);

        DB::table('site_pages')
            ->where('is_published', false)
            ->update(['status' => 'draft']);
    }

    /**
     * 回滚迁移
     */
    public function down(): void
    {
        if (! Schema::hasTable('site_pages')) {
            return;
        }

        Schema::table('site_pages', function (Blueprint $table) {
            foreach (['template', 'blocks', 'status', 'published_at', 'seo_og_image'] as $column) {
                if (Schema::hasColumn('site_pages', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
