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
        Schema::table('nodes', function (Blueprint $table) {
            $table->integer('location', false, true)->nullable(false)->change();
            $table->foreign('location')->references('id')->on('locations');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        try {
            Schema::table('nodes', function (Blueprint $table) {
                $table->dropForeign(['location']);
            });
        } catch (Throwable) {
            //
        }

        Schema::table('nodes', function (Blueprint $table) {
            $table->mediumInteger('location', false, true)->nullable(false)->change();
        });
    }
};
