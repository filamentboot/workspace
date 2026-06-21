<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateActivityLogTable extends Migration
{
    public function up(): void
    {
        $schema    = Schema::connection(config('activitylog.database_connection'));
        $tableName = config('activitylog.table_name');

        if ($schema->hasTable($tableName)) {
            return;
        }

        $schema->create($tableName, function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('log_name')->nullable();
            $table->text('description');
            $table->nullableMorphs('subject', 'subject');
            $table->nullableMorphs('causer', 'causer');
            $table->json('properties')->nullable();
            $table->timestamps();
            $table->index('log_name');
        });
    }

    public function down(): void
    {
        $schema    = Schema::connection(config('activitylog.database_connection'));
        $tableName = config('activitylog.table_name');

        if (! $schema->hasTable($tableName)) {
            return;
        }

        $schema->dropIfExists($tableName);
    }
}
