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
        $db = config('database.default');
        // Same as in the backups migration, we need to handle that plugin messing with the data structure
        // here. If we find a result we'll actually keep the column around since we can maintain that backup
        // limit, but we need to correct the column definition a bit.

        // only attempt this on mysql and mariadb databases
        if (DB::getDriverName() === 'mysql' || DB::getDriverName() === 'mariadb') {
            $results = DB::select('SELECT * FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = \'servers\' AND COLUMN_NAME = \'backup_limit\'', [
                config("database.connections.{$db}.database"),
            ]);

            if (count($results) === 1) {
                Schema::table('servers', function (Blueprint $table) {
                    $table->unsignedInteger('backup_limit')->default(0)->change();
                });
            } else {
                Schema::table('servers', function (Blueprint $table) {
                    $table->unsignedInteger('backup_limit')->default(0)->after('database_limit');
                });
            }
        } else {
            Schema::table('servers', function (Blueprint $table) {
                $table->unsignedInteger('backup_limit')->default(0)->after('database_limit');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('servers', function (Blueprint $table) {
            $table->dropColumn('backup_limit');
        });
    }
};
