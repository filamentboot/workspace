<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 为 filament-tree 迁移 parent_id
     *
     * filament-tree 用整数标记根节点（我们用 0），不能用 NULL 且不能用外键约束。
     * 1. 删除外键约束（根节点 id=0 在表内不存在，无法满足 FK）
     * 2. 将所有根节点的 parent_id NULL → 0
     * 3. 将列改为 NOT NULL DEFAULT 0
     */
    public function up(): void
    {
        // 第一步：删除外键约束
        Schema::table('menus', function (Blueprint $table) {
            $table->dropForeign(['parent_id']);
        });

        // 第二步：将现有 NULL（根节点）改为 0
        DB::table('menus')->whereNull('parent_id')->update(['parent_id' => 0]);

        // 第三步：改列定义为 NOT NULL DEFAULT 0
        Schema::table('menus', function (Blueprint $table) {
            $table->unsignedBigInteger('parent_id')->default(0)->nullable(false)->change();
        });
    }

    public function down(): void
    {
        // 还原：改回 NULL
        Schema::table('menus', function (Blueprint $table) {
            $table->unsignedBigInteger('parent_id')->nullable()->default(null)->change();
        });

        // 将 0 还原为 NULL
        DB::table('menus')->where('parent_id', 0)->update(['parent_id' => null]);

        // 重建外键
        Schema::table('menus', function (Blueprint $table) {
            $table->foreign('parent_id')->references('id')->on('menus')->nullOnDelete();
        });
    }
};
