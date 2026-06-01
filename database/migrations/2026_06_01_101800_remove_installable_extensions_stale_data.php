<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * 清除历史版本 OfficialMarketIndexer 遗留的 Installable Extension 脏数据。
 *
 * 背景：旧版 sync() 方法为每个市场条目创建 status='installable' 的 Extension 记录，
 * 违反数据边界规范。这些记录不对应任何已安装的包，可以安全删除。
 */
return new class extends Migration
{
    /**
     * 删除所有 status='installable' 的 Extension 记录（脏数据）。
     */
    public function up(): void
    {
        DB::table('plugin_platform_extensions')
            ->where('status', 'installable')
            ->delete();
    }

    /**
     * 此迁移不可回滚（数据已清除，无法恢复脏数据）。
     */
    public function down(): void
    {
        // 不可回滚：数据清理属于单向操作
    }
};
