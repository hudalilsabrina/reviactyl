<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('service_variables', function (Blueprint $table) {
            $table->renameColumn('regex', 'rules');
        });

        DB::transaction(function () {
            foreach (DB::table('service_variables')->get() as $variable) {
                $rules = $variable->required ? 'required|regex:'.$variable->rules : 'regex:'.$variable->rules;

                DB::table('service_variables')
                    ->where('id', $variable->id)
                    ->update(['rules' => $rules]);
            }
        });

        Schema::table('service_variables', function (Blueprint $table) {
            $table->dropColumn('required');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('service_variables', function (Blueprint $table) {
            $table->renameColumn('rules', 'regex');
            $table->boolean('required')->default(true)->before('regex');
        });

        DB::transaction(function () {
            foreach (DB::table('service_variables')->get() as $variable) {
                $regex = Str::replace(['required|regex:', 'regex:'], '', $variable->regex);

                DB::table('service_variables')
                    ->where('id', $variable->id)
                    ->update(['regex' => $regex]);
            }
        });
    }
};
