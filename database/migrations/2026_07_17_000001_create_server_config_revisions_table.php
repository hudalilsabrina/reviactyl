<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('server_config_revisions', function (Blueprint $table) {
            $table->unsignedInteger('id', true);
            $table->unsignedInteger('server_id');
            $table->unsignedInteger('author_id');
            $table->unsignedInteger('parent_id')->nullable();
            $table->string('message', 500)->default('Auto-snapshot');
            $table->char('hash', 40)->unique();
            $table->boolean('is_preset')->default(false);
            $table->string('preset_name', 100)->nullable()->unique();
            $table->unsignedInteger('file_count')->default(0);
            $table->timestamps();

            $table->foreign('server_id')->references('id')->on('servers')->cascadeOnDelete();
            $table->foreign('author_id')->references('id')->on('users');
            $table->foreign('parent_id')->references('id')->on('server_config_revisions')->nullOnDelete();

            $table->index(['server_id', 'created_at']);
            $table->index(['server_id', 'is_preset']);
            $table->index('parent_id');
        });

        Schema::create('server_config_files', function (Blueprint $table) {
            $table->unsignedInteger('id', true);
            $table->unsignedInteger('revision_id');
            $table->string('file_path', 500);
            $table->char('content_hash', 64);
            $table->unsignedInteger('content_length')->default(0);
            $table->timestamps();

            $table->foreign('revision_id')->references('id')->on('server_config_revisions')->cascadeOnDelete();

            $table->index(['revision_id', 'file_path']);
        });

        Schema::create('server_config_watch_patterns', function (Blueprint $table) {
            $table->unsignedInteger('id', true);
            $table->unsignedInteger('server_id');
            $table->string('pattern', 255);
            $table->timestamps();

            $table->foreign('server_id')->references('id')->on('servers')->cascadeOnDelete();

            $table->unique(['server_id', 'pattern']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('server_config_watch_patterns');
        Schema::dropIfExists('server_config_files');
        Schema::dropIfExists('server_config_revisions');
    }
};
