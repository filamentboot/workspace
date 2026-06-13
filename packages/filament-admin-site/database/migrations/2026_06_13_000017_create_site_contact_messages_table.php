<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 创建访客询盘消息表
 *
 * 极简询盘表（D-10-15）：姓名 + 电话 + 留言 + 状态 + IP。
 * 无 softDeletes，使用普通 timestamps。
 * status 存 ContactMessageStatus enum value（字符串）。
 */
return new class extends Migration
{
    /**
     * 执行迁移
     */
    public function up(): void
    {
        if (Schema::hasTable('site_contact_messages')) {
            return;
        }

        Schema::create('site_contact_messages', function (Blueprint $table) {
            $table->id();
            $table->string('name')->comment('访客姓名');
            $table->string('phone')->comment('联系电话');
            $table->text('message')->comment('留言内容');
            $table->string('status')->default('unread')->index()->comment('状态（ContactMessageStatus enum value）');
            $table->string('ip')->nullable()->comment('访客 IP 地址');
            $table->timestamps();
            // 无 softDeletes（D-10-15 极简）
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

        Schema::dropIfExists('site_contact_messages');
    }
};
