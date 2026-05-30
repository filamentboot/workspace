<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 创建部门表
     */
    public function up(): void
    {
        if (Schema::hasTable('departments')) {
            return;
        }

        Schema::create('departments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->string('name');
            $table->string('code')->unique();
            $table->foreignId('leader_admin_user_id')->nullable()->constrained('admin_users')->nullOnDelete();
            $table->unsignedInteger('sort')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['parent_id', 'sort']);
            $table->index('is_active');
        });
    }

    /**
     * 回滚部门表
     */
    public function down(): void
    {
        if (! Schema::hasTable('departments')) {
            return;
        }

        Schema::dropIfExists('departments');
    }
};
