<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Used Stock listing fields for public/cabinet panels (owners, VIN).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_vehicles', function (Blueprint $table) {
            $table->unsignedTinyInteger('owners_count')->nullable()->after('mileage_km');
            $table->string('vin', 17)->nullable()->after('owners_count');
        });
    }

    public function down(): void
    {
        Schema::table('stock_vehicles', function (Blueprint $table) {
            $table->dropColumn(['owners_count', 'vin']);
        });
    }
};
