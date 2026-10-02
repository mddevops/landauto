<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('block_definitions', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->string('name');
            $table->string('slug')->unique();
            // Ownership scope must be explicit; Workspace-private and developer scopes are not defined yet.
            $table->boolean('is_official');
            $table->timestamps();
        });

        Schema::create('block_versions', function (Blueprint $table) {
            $table->id();
            // Used Block Definitions are archived, never hard-deleted (BLOCK_SYSTEM.md §68).
            $table->foreignId('block_definition_id')->constrained()->restrictOnDelete();
            $table->string('version', 32);
            $table->json('schema_json');
            $table->timestamp('created_at')->nullable();

            $table->unique(['block_definition_id', 'version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('block_versions');
        Schema::dropIfExists('block_definitions');
    }
};
