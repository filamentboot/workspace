<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 为询盘表补充来源与渠道归因字段（A1）
 *
 * 此前前台各 CTA 已带 data-contact-trigger、Alpine store 也记录了 source，
 * 但 Livewire 组件没有对应属性、表也没有对应列，埋点数据一列没落，
 * 后台看到一条询盘无法判断访客从哪个页面、点哪个按钮、走哪个渠道来的。
 *
 * landing_url / referer 单列宽度取 1024，不建索引：MySQL utf8mb4 下
 * 索引键长上限 3072 字节，长 URL 列加索引会直接建表失败。
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
        if (! Schema::hasTable('site_contact_messages')) {
            return;
        }

        Schema::table('site_contact_messages', function (Blueprint $table) {
            if (! Schema::hasColumn('site_contact_messages', 'source')) {
                $table->string('source', 50)->nullable()->index()->after('ip')
                    ->comment('转化入口标识（floating/hero/nav-desktop 等）');
            }

            if (! Schema::hasColumn('site_contact_messages', 'landing_url')) {
                $table->string('landing_url', 1024)->nullable()->after('source')
                    ->comment('首次落地页完整 URL（首触归因）');
            }

            if (! Schema::hasColumn('site_contact_messages', 'referer')) {
                $table->string('referer', 1024)->nullable()->after('landing_url')
                    ->comment('首次落地时的来源页（Referer 头）');
            }

            foreach (['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content'] as $column) {
                if (! Schema::hasColumn('site_contact_messages', $column)) {
                    $table->string($column, 255)->nullable()->comment('UTM 渠道参数（首触归因）');
                }
            }
        });
    }

    /**
     * 回滚迁移
     */
    public function down(): void
    {
        if (! Schema::hasTable('site_contact_messages')) {
            return;
        }

        Schema::table('site_contact_messages', function (Blueprint $table) {
            foreach ([
                'source',
                'landing_url',
                'referer',
                'utm_source',
                'utm_medium',
                'utm_campaign',
                'utm_term',
                'utm_content',
            ] as $column) {
                if (Schema::hasColumn('site_contact_messages', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
