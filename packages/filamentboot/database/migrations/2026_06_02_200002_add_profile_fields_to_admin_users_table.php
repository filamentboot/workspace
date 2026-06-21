<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * admin_users 表新增个人资料字段
 *
 * 新增：avatar, mobile, last_login_at, last_login_ip, login_failures, onboarding_completed
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('admin_users', function (Blueprint $table) {
            $table->string('avatar')->nullable()->after('nickname')->comment('头像路径');
            $table->string('mobile', 20)->nullable()->after('avatar')->comment('手机号');
            $table->timestamp('last_login_at')->nullable()->after('mobile')->comment('最后登录时间');
            $table->string('last_login_ip', 45)->nullable()->after('last_login_at')->comment('最后登录 IP');
            $table->unsignedSmallInteger('login_failures')->default(0)->after('last_login_ip')->comment('累计登录失败次数');
            $table->boolean('onboarding_completed')->default(false)->after('login_failures')->comment('是否已关闭新手引导');
        });
    }

    public function down(): void
    {
        Schema::table('admin_users', function (Blueprint $table) {
            $table->dropColumn(['avatar', 'mobile', 'last_login_at', 'last_login_ip', 'login_failures', 'onboarding_completed']);
        });
    }
};
