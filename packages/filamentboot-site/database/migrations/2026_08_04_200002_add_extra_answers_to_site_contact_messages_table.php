<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 询盘表增加自定义字段答案列
 *
 * 「不同活动问不同问题」：询盘表单区块可以配出额外的问题（预算区间、房屋面积、
 * 何时开工……），答案落在这一列，形状是有序列表 [{label, value}]。
 *
 * 为什么是列表而不是「问题 => 答案」映射：MySQL 的 JSON 类型会规范化对象、**丢掉键顺序**，
 * 而答案顺序就是表单上问题的先后，属于有意义的信息。JSON 数组的顺序会被保留。
 *
 * 为什么是 JSON 而不是给每个问题建一列：问题由内容编辑随活动增删，
 * 每加一个问题就要一次迁移的设计撑不过三次活动。
 *
 * 为什么不建一张「答案表」：一条询盘最多六个答案，永远和主记录一起读、
 * 一起导出、一起删除，独立成表只多一次 join 和一层生命周期管理。
 *
 * ⚠️ 这一列**不参与站内搜索**：JSON 列里非 ASCII 字符落库时被转成 Unicode
 * 转义序列，LIKE 匹配不到中文（同 site_pages.blocks 的处境，见 Cms\Services\SiteSearch）。
 * 后台按答案筛选属于以后的事。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('site_contact_messages', function (Blueprint $table): void {
            if (! Schema::hasColumn('site_contact_messages', 'extra')) {
                $table->json('extra')->nullable()->after('message')
                    ->comment('自定义字段答案，有序列表 [{label, value}]');
            }
        });
    }

    public function down(): void
    {
        Schema::table('site_contact_messages', function (Blueprint $table): void {
            if (Schema::hasColumn('site_contact_messages', 'extra')) {
                $table->dropColumn('extra');
            }
        });
    }
};
