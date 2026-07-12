<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('external_id')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE users ALTER COLUMN external_id TYPE INTEGER USING (CASE WHEN external_id ~ '^[0-9]+$' THEN external_id::integer ELSE NULL END)");

            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->unsignedInteger('external_id')->nullable()->change();
        });
    }
};
