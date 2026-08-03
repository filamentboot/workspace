<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 创建询盘跟进备注表（A4）
 *
 * 一条询盘对应多条跟进记录，构成后台详情页的跟进时间线。
 *
 * message_id 级联删除（备注离开询盘没有意义）；
 * admin_user_id 置空保留（记录人离职后备注内容仍要留着）。
 */
return new class extends Migration
{
    /**
     * 执行迁移
     */
    public function up(): void
    {
        if (Schema::hasTable('site_contact_message_notes')) {
            return;
        }

        Schema::create('site_contact_message_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('message_id')
                ->comment('所属询盘')
                ->constrained('site_contact_messages')
                ->cascadeOnDelete();
            $table->foreignId('admin_user_id')
                ->nullable()
                ->comment('记录人（admin_users.id）')
                ->constrained('admin_users')
                ->nullOnDelete();
            $table->text('body')->comment('跟进内容');
            $table->timestamps();

            $table->index(['message_id', 'created_at']);
        });
    }

    /**
     * 回滚迁移
     */
    public function down(): void
    {
        Schema::dropIfExists('site_contact_message_notes');
    }
};
