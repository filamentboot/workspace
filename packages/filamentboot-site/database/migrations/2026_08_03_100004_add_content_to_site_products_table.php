<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 为智能产品表补充富文本详情字段
 *
 * 此前产品只有 description_zh/en 这一段纯文本简介，详情页（74 行）除了封面图、
 * 价格和一句描述之外没有任何可承载内容的位置，无法展示参数说明、场景图文和
 * 安装说明，页面观感与真实电商详情页差距过大。
 *
 * 不引入结构化 specs JSON 字段：参数直接写进富文本，避免为一个演示站再造一套
 * 参数表编辑器与渲染组件。
 *
 * 图集不在此迁移：product 的图集走 Media Library 的 gallery 集合，
 * 只需在模型 registerMediaCollections() 注册，无需数据库列。
 */
return new class extends Migration
{
    /**
     * 执行迁移
     */
    public function up(): void
    {
        if (! Schema::hasTable('site_products') || Schema::hasColumn('site_products', 'content_zh')) {
            return;
        }

        Schema::table('site_products', function (Blueprint $table) {
            $table->longText('content_zh')
                ->nullable()
                ->after('description_en')
                ->comment('产品详情富文本（中文）');

            $table->longText('content_en')
                ->nullable()
                ->after('content_zh')
                ->comment('产品详情富文本（英文）');
        });
    }

    /**
     * 回滚迁移
     */
    public function down(): void
    {
        if (! Schema::hasTable('site_products') || ! Schema::hasColumn('site_products', 'content_zh')) {
            return;
        }

        Schema::table('site_products', function (Blueprint $table) {
            $table->dropColumn(['content_zh', 'content_en']);
        });
    }
};
