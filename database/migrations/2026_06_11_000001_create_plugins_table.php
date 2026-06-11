<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 创建插件表
     */
    public function up(): void
    {
        if (Schema::hasTable('plugins')) {
            return;
        }

        Schema::create('plugins', function (Blueprint $table) {
            $table->id();
            $table->string('package_name')->unique();            // vendor/package 格式
            $table->string('slug')->unique();
            $table->string('name');
            $table->string('kind')->default('package');          // package | solution_plugin
            $table->string('source')->default('community');      // official_trusted | official_listed | community
            $table->string('plugin_class')->nullable();
            $table->string('installed_version')->nullable();
            $table->text('description')->nullable();
            $table->json('requires')->nullable();
            $table->json('compatibility')->nullable();
            $table->json('config_overrides')->nullable();
            $table->boolean('is_enabled')->default(false);
            $table->string('init_status')->default('pending'); // pending | running | done | failed
            $table->text('init_log')->nullable();
            $table->timestamp('installed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('is_enabled');
            $table->index('kind');
        });
    }

    /**
     * 回滚插件表
     */
    public function down(): void
    {
        if (! Schema::hasTable('plugins')) {
            return;
        }

        Schema::dropIfExists('plugins');
    }
};
