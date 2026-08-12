<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 创建 site_friend_links 表
 *
 * 由 filamentboot-site:content-type:sync 按「friend_link」内容类型声明生成。
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('site_friend_links')) {
            return;
        }

        Schema::create('site_friend_links', function (Blueprint $table) {
            $table->id();

            $table->string('name', 100)->unique()->comment('名称');
            $table->string('url', 255)->comment('链接');
            $table->string('logo', 255)->nullable()->comment('Logo');
            $table->boolean('is_enabled')->default(true)->comment('启用');
            $table->unsignedInteger('sort')->default(0)->comment('排序权重，数字越小越靠前');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_friend_links');
    }
};
