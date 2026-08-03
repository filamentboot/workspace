<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 创建前台菜单表（#11，供 #17 菜单管理使用）
 *
 * 注意与 database/seeders/SiteMenuSeeder 区分：那个 Seeder 管的是**后台侧边栏**
 * 菜单（写主包 menus 表），本表是**前台导航**菜单（nav / footer）。
 *
 * key 为菜单位标识，前台按固定 key 读取（main、footer）。
 */
return new class extends Migration
{
    /**
     * 执行迁移
     */
    public function up(): void
    {
        if (Schema::hasTable('site_menus')) {
            return;
        }

        Schema::create('site_menus', function (Blueprint $table) {
            $table->id();
            $table->string('key', 50)->unique()->comment('菜单位标识（main / footer 等）');
            $table->string('name')->comment('菜单名称（后台显示）');
            $table->timestamps();
        });
    }

    /**
     * 回滚迁移
     */
    public function down(): void
    {
        Schema::dropIfExists('site_menus');
    }
};
