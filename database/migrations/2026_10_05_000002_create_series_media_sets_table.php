<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Platform Series Media Library (D-103). References a catalog Series by public_id; there is no
 * SQL foreign key across the catalog database boundary (D-102).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('series_media_sets', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->char('catalog_series_public_id', 26);
            $table->string('name', 100);
            $table->char('swatch_hex', 7)->nullable();
            $table->boolean('status')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['catalog_series_public_id', 'name']);
            $table->index(['catalog_series_public_id', 'status', 'sort_order'], 'series_media_sets_series_status_order_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('series_media_sets');
    }
};
