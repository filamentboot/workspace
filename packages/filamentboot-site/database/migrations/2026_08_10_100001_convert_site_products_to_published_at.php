<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * SiteProduct 发布语义统一为 published_at（四期功能清单第 1 档 #2）
 *
 * `site_products.is_published` 是全部内容类型里唯一还用布尔值的一列，且
 * migration 默认值是 `true`——与其余类型「`published_at` 默认 null（未发布）」
 * 语义方向相反。回填按方向对齐：`is_published=true` 的行取 `created_at` 作为
 * 发布时间，`false` 的行保持 null（草稿）。
 *
 * 与 site_cases / site_solutions / news_articles 用同一套列定义与 comment。
 */
return new class extends Migration
{
    /**
     * 加列、回填、删旧列
     */
    public function up(): void
    {
        if (! Schema::hasTable('site_products') || Schema::hasColumn('site_products', 'published_at')) {
            return;
        }

        Schema::table('site_products', function (Blueprint $table) {
            $table->timestamp('published_at')->nullable()->index()->comment('发布时间（null 为草稿）')->after('is_published');
        });

        DB::table('site_products')
            ->where('is_published', true)
            ->update(['published_at' => DB::raw('created_at')]);

        if (Schema::hasColumn('site_products', 'is_published')) {
            Schema::table('site_products', function (Blueprint $table) {
                $table->dropColumn('is_published');
            });
        }
    }

    /**
     * 加回 is_published，按 published_at 是否非空重新派生取值
     */
    public function down(): void
    {
        if (! Schema::hasTable('site_products')) {
            return;
        }

        if (! Schema::hasColumn('site_products', 'is_published')) {
            Schema::table('site_products', function (Blueprint $table) {
                $table->boolean('is_published')->default(true)->index()->comment('是否已发布')->after('category_id');
            });

            DB::table('site_products')->update(['is_published' => false]);
            DB::table('site_products')
                ->whereNotNull('published_at')
                ->update(['is_published' => true]);
        }

        if (Schema::hasColumn('site_products', 'published_at')) {
            Schema::table('site_products', function (Blueprint $table) {
                $table->dropColumn('published_at');
            });
        }
    }
};
