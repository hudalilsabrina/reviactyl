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
        Schema::table('service_variables', function (Blueprint $table) {
            $table->integer('option_id', false, true)->change();
            $table->foreign('option_id')->references('id')->on('service_options');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        try {
            Schema::table('service_variables', function (Blueprint $table) {
                $table->dropForeign(['option_id']);
            });
        } catch (Throwable) {
            //
        }

        Schema::table('service_variables', function (Blueprint $table) {
            $table->mediumInteger('option_id', false, true)->change();
        });
    }
};
