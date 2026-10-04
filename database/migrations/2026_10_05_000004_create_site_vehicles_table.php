<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('site_vehicles', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('site_id')->constrained()->restrictOnDelete();
            $table->char('catalog_series_public_id', 26);
            $table->boolean('status')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['site_id', 'catalog_series_public_id']);
            $table->index('catalog_series_public_id');
        });

        Schema::create('site_vehicle_media_sets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_vehicle_id')->constrained()->cascadeOnDelete();
            $table->foreignId('series_media_set_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['site_vehicle_id', 'series_media_set_id'], 'site_vehicle_media_sets_vehicle_set_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_vehicle_media_sets');
        Schema::dropIfExists('site_vehicles');
    }
};
