<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 弃用旧表 site_page_revisions（批次 1.5c）
 *
 * 数据已在上一条迁移搬进 site_revisions（多态表）。down() 只重建空表结构，
 * 不恢复数据——同 2026_08_05_100000_fix_moved_model_morph_types 的先例，
 * 不可逆操作没有真正的「回滚」，重建结构只是让 migrate:rollback 不报错。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('site_page_revisions');
    }

    public function down(): void
    {
        if (Schema::hasTable('site_page_revisions')) {
            return;
        }

        Schema::create('site_page_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('page_id')
                ->comment('所属页面')
                ->constrained('site_pages')
                ->cascadeOnDelete();
            $table->json('payload')->comment('页面完整快照（标题/区块/SEO/状态等）');
            $table->foreignId('created_by')
                ->nullable()
                ->comment('操作人（admin_users.id）')
                ->constrained('admin_users')
                ->nullOnDelete();
            $table->timestamp('created_at')->nullable()->comment('快照时间');

            $table->index(['page_id', 'created_at']);
        });
    }
};
