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
        Schema::table('api_permissions', function (Blueprint $table) {
            $table->integer('key_id', false, true)->nullable(false)->change();
            $table->foreign('key_id')->references('id')->on('api_keys');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        try {
            Schema::table('api_permissions', function (Blueprint $table) {
                $table->dropForeign(['key_id']);
            });
        } catch (Throwable) {
            //
        }

        Schema::table('api_permissions', function (Blueprint $table) {
            $table->mediumInteger('key_id', false, true)->nullable(false)->change();
        });
    }
};
