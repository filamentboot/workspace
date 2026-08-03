<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 创建前台菜单项表（#11，供 #17 菜单管理使用）
 *
 * ⚠️ parent_id 直接建成 unsignedBigInteger default 0 且**不加外键**。
 *
 * 主包 menus 表当初建成了 nullable 外键，为适配 solution-forest/filament-tree
 * 又不得不追加 2026_06_02_000002_migrate_menus_parent_id_for_tree.php 去掉外键、
 * 把 NULL 刷成 0、再把列改为 nullable(false)——ModelTree 的 defaultParentKey()
 * 约定根节点 parent_id = 0，而 0 不可能是任何一行的主键，外键约束必然冲突。
 * 这个学费交过一次，新表按最终形态直接建。
 */
return new class extends Migration
{
    /**
     * 执行迁移
     */
    public function up(): void
    {
        if (Schema::hasTable('site_menu_items')) {
            return;
        }

        Schema::create('site_menu_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('menu_id')
                ->comment('所属菜单')
                ->constrained('site_menus')
                ->cascadeOnDelete();

            // 树形父节点：0 表示根节点（ModelTree::defaultParentKey()），故不加外键
            $table->unsignedBigInteger('parent_id')->default(0)->comment('父菜单项（0 为根节点）');

            $table->string('type', 20)->default('page')->comment('链接类型：page / url / anchor');
            $table->string('label')->comment('菜单文字（ModelTree 的标题列）');
            $table->string('target', 500)->nullable()->comment('链接目标：页面 slug / 外链 URL / 锚点');
            $table->unsignedInteger('sort')->default(0)->comment('同级排序（ModelTree 的排序列）');
            $table->boolean('open_in_new')->default(false)->comment('是否新窗口打开');
            $table->timestamps();

            $table->index(['menu_id', 'parent_id', 'sort']);
        });
    }

    /**
     * 回滚迁移
     */
    public function down(): void
    {
        Schema::dropIfExists('site_menu_items');
    }
};
