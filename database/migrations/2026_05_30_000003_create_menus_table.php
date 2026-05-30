<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 创建菜单规则表
     */
    public function up(): void
    {
        if (Schema::hasTable('menus')) {
            return;
        }

        Schema::create('menus', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('menus')->nullOnDelete();
            $table->string('title');
            $table->string('icon')->nullable();
            $table->string('route_name')->nullable();
            $table->string('url')->nullable();
            $table->string('permission_name')->nullable();
            $table->unsignedInteger('sort')->default(0);
            $table->boolean('is_active')->default(true);
            $table->string('target')->default('self');
            $table->string('source')->default('core');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['parent_id', 'sort']);
            $table->index(['is_active', 'permission_name']);
        });
    }

    /**
     * 回滚菜单规则表
     */
    public function down(): void
    {
        if (! Schema::hasTable('menus')) {
            return;
        }

        Schema::dropIfExists('menus');
    }
};
