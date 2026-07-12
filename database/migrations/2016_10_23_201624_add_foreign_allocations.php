<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('allocations', function (Blueprint $table) {
            $table->integer('assigned_to', false, true)->nullable()->change();
            $table->integer('node', false, true)->nullable(false)->change();
            $table->foreign('assigned_to')->references('id')->on('servers');
            $table->foreign('node')->references('id')->on('nodes');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        try {
            Schema::table('allocations', function (Blueprint $table) {
                $table->dropForeign(['assigned_to']);
            });
        } catch (Throwable) {
            //
        }

        try {
            Schema::table('allocations', function (Blueprint $table) {
                $table->dropForeign(['node']);
            });
        } catch (Throwable) {
            //
        }

        Schema::table('allocations', function (Blueprint $table) {
            $table->mediumInteger('assigned_to', false, true)->nullable()->change();
            $table->mediumInteger('node', false, true)->nullable(false)->change();
        });
    }
};
