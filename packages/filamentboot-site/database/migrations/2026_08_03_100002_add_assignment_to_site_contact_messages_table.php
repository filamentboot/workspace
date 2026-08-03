<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 为询盘表补充跟进人字段（A4）
 *
 * 此前询盘只有状态（unread/contacted/closed），无法回答「这条谁在跟」，
 * 多人协作时容易重复联系或集体漏掉。
 *
 * 跟进人删除后置空而非连带删除询盘：线索是业务资产，不能因人员离职而消失。
 */
return new class extends Migration
{
    /**
     * 执行迁移
     */
    public function up(): void
    {
        if (! Schema::hasTable('site_contact_messages') || Schema::hasColumn('site_contact_messages', 'assigned_to')) {
            return;
        }

        Schema::table('site_contact_messages', function (Blueprint $table) {
            $table->foreignId('assigned_to')
                ->nullable()
                ->after('status')
                ->comment('跟进人（admin_users.id）')
                ->constrained('admin_users')
                ->nullOnDelete();
        });
    }

    /**
     * 回滚迁移
     */
    public function down(): void
    {
        if (! Schema::hasTable('site_contact_messages') || ! Schema::hasColumn('site_contact_messages', 'assigned_to')) {
            return;
        }

        Schema::table('site_contact_messages', function (Blueprint $table) {
            $table->dropConstrainedForeignId('assigned_to');
        });
    }
};
