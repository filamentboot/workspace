<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 为菜单表新增导航类型字段
     *
     * link_type 取值：
     * route = 路由名称（默认）
     * url   = 自定义 URL
     */
    public function up(): void
    {
        Schema::table('menus', function (Blueprint $table) {
            $table->string('link_type')->default('route')->after('source');
        });
    }

    public function down(): void
    {
        Schema::table('menus', function (Blueprint $table) {
            $table->dropColumn('link_type');
        });
    }
};
