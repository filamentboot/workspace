<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 为菜单表新增节点类型字段
     * menu  = 导航菜单项
     * action = 操作权限节点（不出现在侧边栏）
     */
    public function up(): void
    {
        Schema::table('menus', function (Blueprint $table) {
            $table->string('type')->default('menu')->after('source');
            $table->index(['type', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::table('menus', function (Blueprint $table) {
            $table->dropIndex(['type', 'is_active']);
            $table->dropColumn('type');
        });
    }
};
