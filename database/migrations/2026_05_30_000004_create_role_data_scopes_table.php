<?php

use App\Enums\DataScope;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 创建角色数据范围表
     */
    public function up(): void
    {
        if (Schema::hasTable('role_data_scopes')) {
            return;
        }

        Schema::create('role_data_scopes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('role_id')->unique()->constrained('roles')->cascadeOnDelete();
            $table->string('scope')->default(DataScope::Self->value);
            $table->json('department_ids')->nullable();
            $table->timestamps();
        });
    }

    /**
     * 回滚角色数据范围表
     */
    public function down(): void
    {
        if (! Schema::hasTable('role_data_scopes')) {
            return;
        }

        Schema::dropIfExists('role_data_scopes');
    }
};
