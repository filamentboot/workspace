<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 创建 site_ad_slots 表
 *
 * 由 filamentboot-site:content-type:sync 按「ad_slot」内容类型声明生成。
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('site_ad_slots')) {
            return;
        }

        Schema::create('site_ad_slots', function (Blueprint $table) {
            $table->id();

            $table->string('title', 100)->comment('标题');
            $table->string('image', 255)->nullable()->comment('图片');
            $table->string('link_url', 255)->nullable()->comment('跳转链接');
            $table->string('position', 50)->comment('投放位置');
            $table->timestamp('starts_at')->nullable()->comment('生效开始');
            $table->timestamp('ends_at')->nullable()->comment('生效结束');
            $table->boolean('is_enabled')->default(true)->comment('启用');
            $table->unsignedInteger('sort')->default(0)->comment('排序权重，数字越小越靠前');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_ad_slots');
    }
};
