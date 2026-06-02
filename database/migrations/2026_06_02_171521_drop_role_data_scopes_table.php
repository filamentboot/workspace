<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 删除数据权限配置表
 *
 * PRD 02 简化方案：移除数据权限配置功能（后续版本再加），
 * 数据权限简化为：超级管理员看全部，普通管理员只看自己。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('role_data_scopes');
    }

    public function down(): void
    {
        Schema::create('role_data_scopes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('role_id')->unique()->comment('角色 ID');
            $table->string('scope')->comment('数据权限范围');
            $table->json('department_ids')->nullable()->comment('指定部门 ID 列表');
            $table->timestamps();
        });
    }
};
