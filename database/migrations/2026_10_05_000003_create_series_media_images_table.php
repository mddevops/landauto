<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Immutable prepared images of a platform Series media set, one per angle (D-103).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('series_media_images', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('series_media_set_id')->constrained()->restrictOnDelete();
            $table->string('angle', 16);
            $table->string('path')->unique();
            $table->string('original_name');
            $table->string('mime_type', 32);
            $table->unsignedBigInteger('size_bytes');
            $table->unsignedInteger('width');
            $table->unsignedInteger('height');
            $table->timestamps();

            $table->unique(['series_media_set_id', 'angle']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('series_media_images');
    }
};
