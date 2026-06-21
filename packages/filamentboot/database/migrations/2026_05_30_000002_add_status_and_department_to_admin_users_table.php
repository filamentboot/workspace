<?php

use FilamentAdmin\Enums\AdminUserStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 为管理员表补充状态和部门字段
     */
    public function up(): void
    {
        Schema::table('admin_users', function (Blueprint $table) {
            if (! Schema::hasColumn('admin_users', 'status')) {
                $table->string('status')->default(AdminUserStatus::Active->value)->after('name');
            }

            if (! Schema::hasColumn('admin_users', 'department_id')) {
                $table->foreignId('department_id')
                    ->nullable()
                    ->after('status')
                    ->constrained('departments')
                    ->nullOnDelete();
            }
        });
    }

    /**
     * 回滚管理员新增字段
     */
    public function down(): void
    {
        Schema::table('admin_users', function (Blueprint $table) {
            if (Schema::hasColumn('admin_users', 'department_id')) {
                $table->dropConstrainedForeignId('department_id');
            }

            if (Schema::hasColumn('admin_users', 'status')) {
                $table->dropColumn('status');
            }
        });
    }
};
