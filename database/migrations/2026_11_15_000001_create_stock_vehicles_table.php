<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Workspace Stock inventory (ADR-012 / X-043). Concrete vehicles for sale; Used / Specials bind later.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_vehicles', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->string('condition', 16);
            $table->string('catalog_series_ref', 48);
            $table->string('catalog_equipment_ref', 64)->nullable();
            $table->unsignedSmallInteger('year');
            $table->string('body_name', 120)->nullable();
            $table->string('color_name', 100);
            $table->char('color_swatch_hex', 7)->nullable();
            $table->unsignedInteger('mileage_km')->nullable();
            $table->string('availability', 16);
            $table->unsignedBigInteger('price_minor')->nullable();
            $table->char('currency', 3);
            $table->string('inner_id', 64)->nullable();
            $table->string('stock_number', 16);
            $table->string('status', 16);
            $table->string('pts_document_type', 32)->nullable();
            $table->unsignedSmallInteger('purchased_year')->nullable();
            $table->unsignedTinyInteger('purchased_month')->nullable();
            $table->boolean('on_warranty')->default(false);
            $table->boolean('not_customs_cleared')->default(false);
            $table->text('description')->nullable();
            $table->timestamps();

            $table->unique(['workspace_id', 'stock_number']);
            $table->unique(['workspace_id', 'inner_id']);
            $table->index(['workspace_id', 'status']);
            $table->index(['workspace_id', 'condition']);
            $table->index('catalog_series_ref');
        });

        Schema::table('vehicle_photos', function (Blueprint $table) {
            $table->foreignId('stock_vehicle_id')->nullable()->after('site_vehicle_id')
                ->constrained('stock_vehicles')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('vehicle_photos', function (Blueprint $table) {
            $table->dropConstrainedForeignId('stock_vehicle_id');
        });

        Schema::dropIfExists('stock_vehicles');
    }
};
