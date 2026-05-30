<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 为旧结构补齐软删除字段
     */
    public function up(): void
    {
        $this->addSoftDeletesColumnIfMissing('menus');
        $this->addSoftDeletesColumnIfMissing('departments');
    }

    /**
     * 回滚软删除字段
     */
    public function down(): void
    {
        $this->dropSoftDeletesColumnIfExists('menus');
        $this->dropSoftDeletesColumnIfExists('departments');
    }

    /**
     * 补充 soft delete 字段
     */
    protected function addSoftDeletesColumnIfMissing(string $table): void
    {
        if (! Schema::hasTable($table) || Schema::hasColumn($table, 'deleted_at')) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint): void {
            $blueprint->softDeletes();
        });
    }

    /**
     * 删除 soft delete 字段
     */
    protected function dropSoftDeletesColumnIfExists(string $table): void
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'deleted_at')) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint): void {
            $blueprint->dropSoftDeletes();
        });
    }
};
