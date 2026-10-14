<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('published_runtime_assets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('published_version_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 32);
            $table->longText('content');
            $table->char('content_hash', 64);
            $table->unsignedInteger('byte_size');
            $table->timestamp('created_at')->nullable();

            $table->unique(['published_version_id', 'kind']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('published_runtime_assets');
    }
};
