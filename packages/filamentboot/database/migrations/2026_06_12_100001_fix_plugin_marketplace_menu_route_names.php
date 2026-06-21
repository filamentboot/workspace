<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * 修复 menus 表中「插件市场」子菜单缺少 route_name 的问题。
 *
 * 历史原因：AdminFoundationMenuSeeder 用 title='浏览官方市场'/'已安装插件' 做
 * updateOrCreate 条件，但旧版本已插入 title='官方市场'/'扩展清单'，导致两批记录
 * 共存且旧记录的 route_name 始终为 null，AdminNavigationBuilder 过滤后侧边栏无「插件市场」。
 *
 * 修复策略：
 * - 将旧标题 '官方市场' → '浏览官方市场'，补 route_name
 * - 将旧标题 '扩展清单' → '已安装插件'，补 route_name + permission_name
 * - 回滚还原标题与清空 route_name
 */
return new class extends Migration
{
    public function up(): void
    {
        // 找到「插件市场」顶层分组的 ID
        $groupId = DB::table('menus')
            ->where('title', '插件市场')
            ->where('parent_id', 0)
            ->value('id');

        if (! $groupId) {
            return;
        }

        // 官方市场 → 设置 route_name 并统一标题
        DB::table('menus')
            ->where('parent_id', $groupId)
            ->where('title', '官方市场')
            ->update([
                'title'      => '浏览官方市场',
                'route_name' => 'filament.admin.pages.marketplace',
                'icon'       => 'heroicon-o-shopping-bag',
                'sort'       => 10,
                'updated_at' => now(),
            ]);

        // 扩展清单 → 设置 route_name 并统一标题
        DB::table('menus')
            ->where('parent_id', $groupId)
            ->where('title', '扩展清单')
            ->update([
                'title'           => '已安装插件',
                'route_name'      => 'filament.admin.resources.plugins.index',
                'permission_name' => 'view_any_plugin',
                'icon'            => 'heroicon-o-puzzle-piece',
                'sort'            => 20,
                'updated_at'      => now(),
            ]);
    }

    public function down(): void
    {
        $groupId = DB::table('menus')
            ->where('title', '插件市场')
            ->where('parent_id', 0)
            ->value('id');

        if (! $groupId) {
            return;
        }

        DB::table('menus')
            ->where('parent_id', $groupId)
            ->where('title', '浏览官方市场')
            ->update([
                'title'      => '官方市场',
                'route_name' => null,
                'updated_at' => now(),
            ]);

        DB::table('menus')
            ->where('parent_id', $groupId)
            ->where('title', '已安装插件')
            ->update([
                'title'           => '扩展清单',
                'route_name'      => null,
                'permission_name' => null,
                'updated_at'      => now(),
            ]);
    }
};
