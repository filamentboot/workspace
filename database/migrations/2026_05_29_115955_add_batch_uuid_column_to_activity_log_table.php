<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddBatchUuidColumnToActivityLogTable extends Migration
{
    public function up(): void
    {
        $schema    = Schema::connection(config('activitylog.database_connection'));
        $tableName = config('activitylog.table_name');

        if (! $schema->hasTable($tableName) || $schema->hasColumn($tableName, 'batch_uuid')) {
            return;
        }

        $schema->table($tableName, function (Blueprint $table) {
            $table->uuid('batch_uuid')->nullable()->after('properties');
        });
    }

    public function down(): void
    {
        $schema    = Schema::connection(config('activitylog.database_connection'));
        $tableName = config('activitylog.table_name');

        if (! $schema->hasTable($tableName) || ! $schema->hasColumn($tableName, 'batch_uuid')) {
            return;
        }

        $schema->table($tableName, function (Blueprint $table) {
            $table->dropColumn('batch_uuid');
        });
    }
}
