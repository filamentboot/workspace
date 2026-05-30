<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 升级旧版菜单表到当前结构
     */
    public function up(): void
    {
        if (! Schema::hasTable('menus')) {
            return;
        }

        Schema::table('menus', function (Blueprint $table): void {
            if (! Schema::hasColumn('menus', 'title')) {
                $table->string('title')->nullable()->after('parent_id');
            }

            if (! Schema::hasColumn('menus', 'route_name')) {
                $table->string('route_name')->nullable()->after('icon');
            }

            if (! Schema::hasColumn('menus', 'url')) {
                $table->string('url')->nullable()->after('route_name');
            }

            if (! Schema::hasColumn('menus', 'target')) {
                $table->string('target')->default('self')->after('is_active');
            }

            if (! Schema::hasColumn('menus', 'source')) {
                $table->string('source')->default('core')->after('target');
            }
        });

        $menus = DB::table('menus')->get();

        foreach ($menus as $menu) {
            $route = $menu->route ?? null;

            $routeName = $menu->route_name ?? null;
            $url       = $menu->url ?? null;

            if (blank($routeName) && blank($url) && filled($route)) {
                if (
                    str_starts_with($route, '/') ||
                    str_starts_with($route, 'http://') ||
                    str_starts_with($route, 'https://')
                ) {
                    $url = $route;
                } else {
                    $routeName = $route;
                }
            }

            DB::table('menus')
                ->where('id', $menu->id)
                ->update([
                    'title'      => $menu->title ?? $menu->name ?? null,
                    'route_name' => $routeName,
                    'url'        => $url,
                    'target'     => $menu->target ?? 'self',
                    'source'     => $menu->source ?? 'core',
                ]);
        }
    }

    /**
     * 回滚升级字段
     */
    public function down(): void
    {
        if (! Schema::hasTable('menus')) {
            return;
        }

        Schema::table('menus', function (Blueprint $table): void {
            foreach (['title', 'route_name', 'url', 'target', 'source'] as $column) {
                if (Schema::hasColumn('menus', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
