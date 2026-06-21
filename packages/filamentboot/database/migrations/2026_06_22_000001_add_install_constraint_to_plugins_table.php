<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 为 plugins 表添加 install_constraint 列（CR-01 修复）
 *
 * 将"安装约束"与"已解析版本"两个概念拆分：
 * - install_constraint：用户或目录写入的版本约束（如 ^0.5.0），供 composer require 使用。
 * - installed_version：syncFromInstalled 从 installed.json 写入的实际已解析版本（如 0.5.0）。
 *
 * 向后兼容：列可空（nullable）；runComposerInstall 无 install_constraint 时回落至 installed_version。
 */
return new class extends Migration
{
    /**
     * 添加 install_constraint 列（nullable，不影响已有行）。
     */
    public function up(): void
    {
        if (! Schema::hasTable('plugins')) {
            return;
        }

        Schema::table('plugins', function (Blueprint $table) {
            if (! Schema::hasColumn('plugins', 'install_constraint')) {
                $table->string('install_constraint')->nullable()->after('installed_version')
                    ->comment('composer require 使用的版本约束（如 ^0.5.0）；与 installed_version 分离，避免 syncFromInstalled 覆盖约束。');
            }
        });
    }

    /**
     * 移除 install_constraint 列。
     */
    public function down(): void
    {
        if (! Schema::hasTable('plugins')) {
            return;
        }

        Schema::table('plugins', function (Blueprint $table) {
            if (Schema::hasColumn('plugins', 'install_constraint')) {
                $table->dropColumn('install_constraint');
            }
        });
    }
};
