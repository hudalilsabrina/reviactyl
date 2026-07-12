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
        Schema::table('service_packs', function (Blueprint $table) {
            $table->dropForeign(['option']);

            $table->renameColumn('option', 'option_id');
            $table->foreign('option_id')->references('id')->on('service_options');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        try {
            Schema::table('service_packs', function (Blueprint $table) {
                $table->dropForeign(['option_id']);
            });
        } catch (Throwable) {
            //
        }

        Schema::table('service_packs', function (Blueprint $table) {
            $table->renameColumn('option_id', 'option');
            $table->foreign('option')->references('id')->on('service_options');
        });
    }
};
