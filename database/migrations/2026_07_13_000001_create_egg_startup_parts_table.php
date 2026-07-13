<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('egg_startup_parts', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('egg_id');
            $table->string('name');
            $table->string('value');
            $table->string('description')->nullable();
            $table->boolean('default_enabled')->default(false);
            $table->boolean('required')->default(false);
            $table->integer('sort_order')->default(0);
            $table->string('group_name')->nullable();
            $table->timestamps();

            $table->foreign('egg_id')->references('id')->on('eggs')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('egg_startup_parts');
    }
};
