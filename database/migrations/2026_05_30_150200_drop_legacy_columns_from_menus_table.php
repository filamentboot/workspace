<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 删除旧版菜单遗留字段
     */
    public function up(): void
    {
        if (! Schema::hasTable('menus')) {
            return;
        }

        Schema::table('menus', function (Blueprint $table): void {
            $columns = [];

            foreach (['name', 'route'] as $column) {
                if (Schema::hasColumn('menus', $column)) {
                    $columns[] = $column;
                }
            }

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }

    /**
     * 回滚旧字段
     */
    public function down(): void
    {
        if (! Schema::hasTable('menus')) {
            return;
        }

        Schema::table('menus', function (Blueprint $table): void {
            if (! Schema::hasColumn('menus', 'name')) {
                $table->string('name')->nullable()->after('title');
            }

            if (! Schema::hasColumn('menus', 'route')) {
                $table->string('route')->nullable()->after('url');
            }
        });
    }
};
