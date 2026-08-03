<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 为装修案例表补充业主见证字段
 *
 * 营销型官网需要客户见证，但官网没有「商品评论」这种物件——它的对应物是
 * 业主本人对已完工项目的评价。现有 site_cases 已经描述了项目本身（风格、
 * 户型、面积、预算、智能配置、图集、正文），唯独缺「业主自己怎么说」。
 *
 * 因此扩展现有表而非新建 Testimonial 模型：见证天然依附于案例，
 * 独立模型会造出一堆没有项目背景的孤立引言，可信度反而更低。
 *
 * 业主头像走 Media Library 的 avatar 集合（模型侧注册），不占数据库列。
 */
return new class extends Migration
{
    /**
     * 执行迁移
     */
    public function up(): void
    {
        if (! Schema::hasTable('site_cases') || Schema::hasColumn('site_cases', 'customer_name')) {
            return;
        }

        Schema::table('site_cases', function (Blueprint $table) {
            $table->string('customer_name')
                ->nullable()
                ->after('content_en')
                ->comment('业主称呼（如「张先生」，不存全名）');

            $table->text('customer_quote')
                ->nullable()
                ->after('customer_name')
                ->comment('业主评价原话');

            $table->string('customer_meta')
                ->nullable()
                ->after('customer_quote')
                ->comment('业主附注（如「万科城市之光 · 入住 8 个月」）');
        });
    }

    /**
     * 回滚迁移
     */
    public function down(): void
    {
        if (! Schema::hasTable('site_cases') || ! Schema::hasColumn('site_cases', 'customer_name')) {
            return;
        }

        Schema::table('site_cases', function (Blueprint $table) {
            $table->dropColumn(['customer_name', 'customer_quote', 'customer_meta']);
        });
    }
};
