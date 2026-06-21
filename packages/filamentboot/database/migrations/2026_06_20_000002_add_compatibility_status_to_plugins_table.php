<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 为 plugins 表添加 compatibility_status 列（CR-04 修复）
 *
 * 三态兼容性由 plugin:scan 写入，供 PluginResource 和 MarketplacePage 直接读取，
 * 避免运行时发起 HTTP 请求（D-12-15 安装门逻辑）。
 */
return new class extends Migration
{
    /**
     * 添加 compatibility_status 列（默认 'unknown'）。
     */
    public function up(): void
    {
        if (! Schema::hasTable('plugins')) {
            return;
        }

        Schema::table('plugins', function (Blueprint $table) {
            if (! Schema::hasColumn('plugins', 'compatibility_status')) {
                $table->string('compatibility_status')->default('unknown')->after('post_install_data');
            }
        });
    }

    /**
     * 删除 compatibility_status 列。
     */
    public function down(): void
    {
        if (! Schema::hasTable('plugins')) {
            return;
        }

        Schema::table('plugins', function (Blueprint $table) {
            if (Schema::hasColumn('plugins', 'compatibility_status')) {
                $table->dropColumn('compatibility_status');
            }
        });
    }
};
