<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workspace_vehicles', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->char('catalog_series_public_id', 26);
            $table->string('custom_name', 120)->nullable();
            $table->text('custom_description')->nullable();
            $table->boolean('status')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['workspace_id', 'catalog_series_public_id']);
            $table->index('catalog_series_public_id');
        });

        Schema::create('workspace_vehicle_media_sets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_vehicle_id')->constrained()->cascadeOnDelete();
            $table->foreignId('series_media_set_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['workspace_vehicle_id', 'series_media_set_id'], 'workspace_vehicle_media_sets_unique');
        });

        Schema::table('site_vehicles', function (Blueprint $table) {
            $table->string('custom_name', 120)->nullable()->after('catalog_series_public_id');
            $table->text('custom_description')->nullable()->after('custom_name');
            $table->foreignId('source_workspace_vehicle_id')->nullable()->after('site_id')
                ->constrained('workspace_vehicles')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('site_vehicles', function (Blueprint $table) {
            $table->dropConstrainedForeignId('source_workspace_vehicle_id');
            $table->dropColumn(['custom_name', 'custom_description']);
        });

        Schema::dropIfExists('workspace_vehicle_media_sets');
        Schema::dropIfExists('workspace_vehicles');
    }
};
