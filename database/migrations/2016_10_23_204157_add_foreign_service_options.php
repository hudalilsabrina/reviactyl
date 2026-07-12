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
        Schema::table('service_options', function (Blueprint $table) {
            $table->integer('parent_service', false, true)->change();
            $table->foreign('parent_service')->references('id')->on('services');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        try {
            Schema::table('service_options', function (Blueprint $table) {
                $table->dropForeign(['parent_service']);
            });
        } catch (Throwable) {
            //
        }

        Schema::table('service_options', function (Blueprint $table) {
            $table->mediumInteger('parent_service', false, true)->change();
        });
    }
};
