<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 创建页面版本快照表（#11，供 #15 版本回滚使用）
 *
 * 每次保存页面写入一条快照，回滚时产生新版本而非删除历史，
 * 保证审核链路可追溯。
 *
 * 只有 created_at 没有 updated_at：快照一旦写入即不可变，
 * 对应模型的 const UPDATED_AT = null。
 */
return new class extends Migration
{
    /**
     * 执行迁移
     */
    public function up(): void
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

    /**
     * 回滚迁移
     */
    public function down(): void
    {
        Schema::dropIfExists('site_page_revisions');
    }
};
