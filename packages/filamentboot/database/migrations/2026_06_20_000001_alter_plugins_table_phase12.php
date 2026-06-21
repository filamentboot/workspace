<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 12 插件表结构调整：移除协议专属字段，新增 post_install_data JSON 列。
 */
return new class extends Migration
{
    /**
     * 移除 requires/compatibility/config_overrides，添加 post_install_data。
     */
    public function up(): void
    {
        if (! Schema::hasTable('plugins')) {
            return;
        }

        Schema::table('plugins', function (Blueprint $table) {
            $columnsToDrop = [];

            if (Schema::hasColumn('plugins', 'requires')) {
                $columnsToDrop[] = 'requires';
            }
            if (Schema::hasColumn('plugins', 'compatibility')) {
                $columnsToDrop[] = 'compatibility';
            }
            if (Schema::hasColumn('plugins', 'config_overrides')) {
                $columnsToDrop[] = 'config_overrides';
            }

            if (! empty($columnsToDrop)) {
                $table->dropColumn($columnsToDrop);
            }

            if (! Schema::hasColumn('plugins', 'post_install_data')) {
                $table->json('post_install_data')->nullable()->after('service_provider');
            }
        });
    }

    /**
     * 回滚：删除 post_install_data，恢复 requires/compatibility/config_overrides。
     */
    public function down(): void
    {
        if (! Schema::hasTable('plugins')) {
            return;
        }

        Schema::table('plugins', function (Blueprint $table) {
            if (Schema::hasColumn('plugins', 'post_install_data')) {
                $table->dropColumn('post_install_data');
            }

            if (! Schema::hasColumn('plugins', 'requires')) {
                $table->json('requires')->nullable();
            }
            if (! Schema::hasColumn('plugins', 'compatibility')) {
                $table->json('compatibility')->nullable();
            }
            if (! Schema::hasColumn('plugins', 'config_overrides')) {
                $table->json('config_overrides')->nullable();
            }
        });
    }
};
