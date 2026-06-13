<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 为 plugins 表新增 settings_page_slug 列
 *
 * 存储插件设置页的 Filament slug（如 settings/oss），供插件列表页渲染「设置」按钮。
 * 由 plugin:scan 从 extra.filament-admin.settings_page_slug 自动写入，无需手填。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plugins', function (Blueprint $table): void {
            $table->string('settings_page_slug')->nullable()->after('plugin_class');
        });
    }

    public function down(): void
    {
        Schema::table('plugins', function (Blueprint $table): void {
            $table->dropColumn('settings_page_slug');
        });
    }
};
