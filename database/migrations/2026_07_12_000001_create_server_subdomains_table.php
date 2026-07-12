<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('server_subdomains', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('server_id');
            $table->string('subdomain', 63);
            $table->string('domain', 255);
            $table->string('record_id', 64)->nullable();
            $table->string('ip_address', 45);
            $table->boolean('is_auto_generated')->default(false);
            $table->timestamps();

            $table->foreign('server_id')->references('id')->on('servers')->cascadeOnDelete();
            $table->unique(['server_id', 'domain']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('server_subdomains');
    }
};
