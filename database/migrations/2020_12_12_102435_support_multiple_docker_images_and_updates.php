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
        Schema::table('eggs', function (Blueprint $table) {
            $table->json('docker_images')->after('docker_image')->nullable();
            $table->text('update_url')->after('docker_images')->nullable();
        });

        switch (DB::getPdo()->getAttribute(PDO::ATTR_DRIVER_NAME)) {
            case 'mysql':
                DB::table('eggs')->update(['docker_images' => DB::raw('JSON_ARRAY(docker_image)')]);
                break;
            case 'pgsql':
                DB::table('eggs')->update(['docker_images' => DB::raw('jsonb_build_array(docker_image)')]);
                break;
        }

        Schema::table('eggs', function (Blueprint $table) {
            $table->dropColumn('docker_image');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('eggs', function (Blueprint $table) {
            $table->text('docker_image')->nullable()->after('docker_images');
        });

        DB::table('eggs')
            ->select(['id', 'docker_images'])
            ->orderBy('id')
            ->chunkById(100, function ($eggs): void {
                foreach ($eggs as $egg) {
                    $dockerImage = null;
                    $images = json_decode((string) $egg->docker_images, true);

                    if (is_array($images)) {
                        $dockerImage = $images[0] ?? null;
                    }

                    DB::table('eggs')
                        ->where('id', $egg->id)
                        ->update(['docker_image' => $dockerImage]);
                }
            });

        Schema::table('eggs', function (Blueprint $table) {
            $table->dropColumn('docker_images');
            $table->dropColumn('update_url');
        });
    }
};
