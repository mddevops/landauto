<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('site_offers', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('site_vehicle_id')->constrained()->cascadeOnDelete();
            $table->char('catalog_equipment_public_id', 26);
            $table->unsignedBigInteger('price_minor');
            $table->unsignedBigInteger('rrp_minor')->nullable();
            $table->char('currency', 3);
            $table->string('availability', 16)->nullable();
            $table->string('badge', 40)->nullable();
            $table->boolean('status')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['site_vehicle_id', 'sort_order']);
            $table->index('catalog_equipment_public_id');
        });

        Schema::create('site_offer_benefits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_offer_id')->constrained()->cascadeOnDelete();
            $table->string('type', 16);
            $table->unsignedBigInteger('amount_minor');
            $table->string('label', 100)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['site_offer_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_offer_benefits');
        Schema::dropIfExists('site_offers');
    }
};
