<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A Stock vehicle shown on one Site (owner 2026-11-21). Price and photos here override
 * the Workspace Stock card for this Site only.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('site_stock_listings', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('site_id')->constrained()->cascadeOnDelete();
            $table->foreignId('stock_vehicle_id')->constrained('stock_vehicles')->cascadeOnDelete();
            $table->unsignedBigInteger('price_minor')->nullable();
            $table->char('currency', 3)->nullable();
            $table->boolean('photos_custom')->default(false);
            $table->timestamps();

            $table->unique(['site_id', 'stock_vehicle_id']);
        });

        Schema::table('vehicle_photos', function (Blueprint $table) {
            $table->foreignId('site_stock_listing_id')->nullable()->after('stock_vehicle_id')
                ->constrained('site_stock_listings')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('vehicle_photos', function (Blueprint $table) {
            $table->dropConstrainedForeignId('site_stock_listing_id');
        });

        Schema::dropIfExists('site_stock_listings');
    }
};
