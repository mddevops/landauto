<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pages', function (Blueprint $table) {
            $table->foreignId('site_section_id')->nullable()->after('site_id')->constrained()->restrictOnDelete();
            $table->string('section_role', 10)->nullable()->after('site_section_id');
            $table->unique(['site_section_id', 'section_role']);
        });

        Schema::table('site_vehicles', function (Blueprint $table) {
            $table->string('slug', 100)->nullable()->after('catalog_series_ref');
            $table->index(['site_id', 'slug']);
        });

        Schema::create('site_vehicle_slug_redirects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_vehicle_id')->constrained()->cascadeOnDelete();
            $table->string('slug', 100);
            $table->timestamps();

            $table->unique(['site_vehicle_id', 'slug']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_vehicle_slug_redirects');

        Schema::table('site_vehicles', function (Blueprint $table) {
            $table->dropIndex(['site_id', 'slug']);
            $table->dropColumn('slug');
        });

        Schema::table('pages', function (Blueprint $table) {
            $table->dropUnique(['site_section_id', 'section_role']);
            $table->dropConstrainedForeignId('site_section_id');
            $table->dropColumn('section_role');
        });
    }
};
