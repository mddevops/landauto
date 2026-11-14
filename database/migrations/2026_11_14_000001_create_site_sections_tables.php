<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('site_sections', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('site_id')->constrained()->restrictOnDelete();
            $table->string('type', 20);
            $table->string('title', 120);
            $table->string('prefix', 60);
            $table->boolean('enabled')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['site_id', 'type']);
            $table->unique(['site_id', 'prefix']);
        });

        Schema::create('site_section_redirects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_id')->constrained()->restrictOnDelete();
            $table->foreignId('site_section_id')->constrained()->cascadeOnDelete();
            $table->string('prefix', 60);
            $table->timestamps();

            $table->unique(['site_id', 'prefix']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_section_redirects');
        Schema::dropIfExists('site_sections');
    }
};
