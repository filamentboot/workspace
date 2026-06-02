<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 重命名 admin_users 表字段
 *
 * username → account（账号）
 * name     → nickname（昵称）
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('admin_users', function (Blueprint $table) {
            $table->renameColumn('username', 'account');
            $table->renameColumn('name', 'nickname');
        });
    }

    public function down(): void
    {
        Schema::table('admin_users', function (Blueprint $table) {
            $table->renameColumn('account', 'username');
            $table->renameColumn('nickname', 'name');
        });
    }
};
