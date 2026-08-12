<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 把内容表的 `sort` 从 unsignedInteger 改成 integer（允许负数）
 *
 * `sort` 一直被说成「排序权重，数字越小越靠前」，但列类型是 `unsignedInteger`，
 * **下界就是 0**。默认值也是 0，于是一批内容全在 0 上时，运营想把某一条提到最前面
 * 是做不到的——只能反过来把其余每一条都调大。内容一多这就不是个可用的操作。
 *
 * 症状很隐蔽：后台填 `-1` 不会有校验提示，是数据库层面 out of range 报错，
 * 表单直接抛 500；填 `0` 又和别人一样。所以这不是「不好用」，是**这个字段的
 * 语义从来没有真正成立过**。
 *
 * 改成有符号之后语义才闭合：
 *
 *     负数 = 置顶（越小越靠前）    0 = 默认    正数 = 下沉
 *
 * 范围与索引都不受影响：`INT` 有符号是 -2147483648..2147483647，
 * 原来能存的正数一个不少，存量数据不需要搬。
 *
 * **`site_menu_items` 与 `site_regions` 有意不在名单里**：
 * 前者的 `sort` 由 filament-tree 的拖拽控件写，值域是它自己维护的 0..N，
 * 人不直接填；后者是行政区划的官方顺序，也不是内容排序。
 * 两处都没有「置顶」这个动作，跟着改只会扩大改动面。
 */
return new class extends Migration
{
    /**
     * 执行迁移
     */
    public function up(): void
    {
        foreach ($this->tables() as $table) {
            if (! Schema::hasColumn($table, 'sort')) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint): void {
                $blueprint->integer('sort')->default(0)->comment('排序权重（负数置顶，0 默认，正数下沉）')->change();
            });
        }
    }

    /**
     * 回滚迁移
     *
     * 改回无符号会让已存进去的负数直接溢出成大整数——那比保持有符号糟得多。
     * 所以先把负数抬回 0 再改类型，宁可丢掉置顶设置，也不能把顺序搅成随机的。
     */
    public function down(): void
    {
        foreach ($this->tables() as $table) {
            if (! Schema::hasColumn($table, 'sort')) {
                continue;
            }

            DB::table($table)->where('sort', '<', 0)->update(['sort' => 0]);

            Schema::table($table, function (Blueprint $blueprint): void {
                $blueprint->unsignedInteger('sort')->default(0)->comment('排序权重')->change();
            });
        }
    }

    /**
     * 内容表清单
     *
     * @return list<string>
     */
    private function tables(): array
    {
        return [
            'site_pages',
            'site_cases',
            'site_solutions',
            'site_products',
            'site_news_articles',
            'site_packages',
            'site_banners',
            'site_city_pages',
            'site_case_categories',
            'site_product_categories',
            'site_news_categories',
        ];
    }
};
