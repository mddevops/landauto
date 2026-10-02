<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Draft Block Instances; the published representation is decided by X-002.
        Schema::create('page_blocks', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            // Page deletion requires an explicit domain workflow; instances never disappear implicitly.
            $table->foreignId('page_id')->constrained()->restrictOnDelete();
            // Pinned version (BLOCK_SYSTEM.md §6); the definition is derived from it.
            $table->foreignId('block_version_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
            $table->json('state_json');
            $table->timestamps();

            $table->index(['page_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('page_blocks');
    }
};
