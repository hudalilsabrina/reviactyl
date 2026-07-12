<?php

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::transaction(function () {
            DB::table('api_keys')->get()->each(function ($item) {
                try {
                    $decrypted = Crypt::decrypt($item->secret);
                } catch (DecryptException) {
                    $decrypted = Str::random(32);
                } finally {
                    DB::table('api_keys')->where('id', $item->id)->update([
                        'secret' => $decrypted,
                    ]);
                }
            });
        });

        Schema::table('api_keys', function (Blueprint $table) {
            $table->dropColumn('public');
            $table->renameColumn('secret', 'token');
        });

        Schema::table('api_keys', function (Blueprint $table) {
            $table->char('token', 32)->change();
            $table->unique('token');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        try {
            Schema::table('api_keys', function (Blueprint $table) {
                $table->dropUnique(['token']);
            });
        } catch (Throwable) {
            //
        }

        Schema::table('api_keys', function (Blueprint $table) {
            $table->text('token')->nullable()->change();
        });

        Schema::table('api_keys', function (Blueprint $table) {
            $table->renameColumn('token', 'secret');
        });

        Schema::table('api_keys', function (Blueprint $table) {
            $table->text('secret')->nullable()->change();
            $table->char('public', 16)->after('user_id');
        });

        DB::transaction(function () {
            DB::table('api_keys')->get()->each(function ($item) {
                DB::table('api_keys')->where('id', $item->id)->update([
                    'public' => Str::random(16),
                    'secret' => Crypt::encrypt($item->secret),
                ]);
            });
        });
    }
};
