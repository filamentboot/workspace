<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 为 plugins 表添加 service_provider 字段
     *
     * plugin_class 存储 Filament Plugin 接口实现，vendor:publish --provider
     * 需要的是 ServiceProvider 子类，两者不同（WR-02 修复）。
     * 新增 service_provider 字段专门存储 ServiceProvider 类名。
     */
    public function up(): void
    {
        if (! Schema::hasTable('plugins')) {
            return;
        }

        Schema::table('plugins', function (Blueprint $table) {
            // ServiceProvider 类名，用于 vendor:publish --provider（与 plugin_class 分离）
            $table->string('service_provider')->nullable()->after('plugin_class');
        });
    }

    /**
     * 回滚：删除 service_provider 字段
     */
    public function down(): void
    {
        if (! Schema::hasTable('plugins')) {
            return;
        }

        Schema::table('plugins', function (Blueprint $table) {
            $table->dropColumn('service_provider');
        });
    }
};
