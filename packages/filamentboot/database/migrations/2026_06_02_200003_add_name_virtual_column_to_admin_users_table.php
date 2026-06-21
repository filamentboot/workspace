<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 为 admin_users 表添加虚拟列 name（指向 nickname）
 *
 * 背景：name 字段在 2026_06_02_200001 迁移中已重命名为 nickname。
 * 第三方包 alizharb/filament-activity-log 硬编码查询 `name` 列，
 * 通过添加虚拟列保持向后兼容，避免改动 vendor 代码。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('admin_users', function (Blueprint $table) {
            $table->string('name', 255)
                ->virtualAs('nickname')
                ->nullable()
                ->after('nickname')
                ->comment('兼容字段，虚拟列（= nickname）');
        });
    }

    public function down(): void
    {
        Schema::table('admin_users', function (Blueprint $table) {
            $table->dropColumn('name');
        });
    }
};
