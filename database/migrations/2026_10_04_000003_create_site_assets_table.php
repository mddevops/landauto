<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('site_assets', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('site_id')->constrained()->restrictOnDelete();
            $table->string('path')->unique();
            $table->string('original_name');
            $table->string('mime_type', 32);
            $table->unsignedBigInteger('size_bytes');
            $table->unsignedInteger('width');
            $table->unsignedInteger('height');
            $table->timestamps();

            $table->index(['site_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_assets');
    }
};
