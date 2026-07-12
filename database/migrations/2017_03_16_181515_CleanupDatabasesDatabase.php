<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public $withinTransaction = false;

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        try {
            Schema::table('databases', function (Blueprint $table) {
                $table->dropForeign(['db_server']);
            });
        } catch (Throwable) {
            //
        }

        Schema::table('databases', function (Blueprint $table) {
            $table->renameColumn('db_server', 'database_host_id');
        });

        try {
            Schema::table('databases', function (Blueprint $table) {
                $table->foreign('database_host_id')->references('id')->on('database_hosts');
            });
        } catch (Throwable) {
            // Ignore foreign key recreation issues on legacy or partially rolled back schemas.
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        try {
            Schema::table('databases', function (Blueprint $table) {
                $table->dropForeign(['database_host_id']);
            });
        } catch (Throwable) {
            //
        }

        Schema::table('databases', function (Blueprint $table) {
            $table->renameColumn('database_host_id', 'db_server');
        });

        try {
            Schema::table('databases', function (Blueprint $table) {
                $table->foreign('db_server')->references('id')->on('database_hosts');
            });
        } catch (Throwable) {
            // Ignore foreign key recreation issues on legacy or partially rolled back schemas.
        }
    }
};
