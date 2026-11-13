<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * ADR-010: customer vehicles reference the auto.ru catalog by string refs, own copies of their
 * photos, may carry default trims with prices and belong to collections. Catalog V2 public_id
 * references cannot be mapped to auto.ru rows, so rows holding them are removed (outside
 * production only; staging had none).
 */
return new class extends Migration
{
    private const LEGACY_TABLES = ['site_offer_benefits', 'site_offers', 'site_vehicle_media_sets', 'workspace_vehicle_media_sets', 'site_vehicles', 'workspace_vehicles', 'series_media_images', 'series_media_sets'];

    public function up(): void
    {
        $this->removeLegacyRows();

        Schema::dropIfExists('site_vehicle_media_sets');
        Schema::dropIfExists('workspace_vehicle_media_sets');

        // MySQL may back the site_id / workspace_id FKs with the composite unique indexes
        // that also hold the old catalog public_id; give those FKs a dedicated index first.
        Schema::table('site_vehicles', function (Blueprint $table) {
            $table->index('site_id');
        });
        Schema::table('site_vehicles', function (Blueprint $table) {
            $table->dropUnique(['site_id', 'catalog_series_public_id']);
            $table->dropIndex(['catalog_series_public_id']);
        });
        Schema::table('site_vehicles', fn (Blueprint $table) => $table->dropColumn('catalog_series_public_id'));
        Schema::table('site_vehicles', function (Blueprint $table) {
            $table->string('catalog_series_ref', 48)->default('')->after('site_id');
            $table->string('primary_angle', 16)->nullable()->after('custom_description');
            $table->unique(['site_id', 'catalog_series_ref']);
            $table->index('catalog_series_ref');
        });

        Schema::table('workspace_vehicles', function (Blueprint $table) {
            $table->index('workspace_id');
        });
        Schema::table('workspace_vehicles', function (Blueprint $table) {
            $table->dropUnique(['workspace_id', 'catalog_series_public_id']);
            $table->dropIndex(['catalog_series_public_id']);
        });
        Schema::table('workspace_vehicles', fn (Blueprint $table) => $table->dropColumn('catalog_series_public_id'));
        Schema::table('workspace_vehicles', function (Blueprint $table) {
            $table->string('catalog_series_ref', 48)->default('')->after('workspace_id');
            $table->string('primary_angle', 16)->nullable()->after('custom_description');
            $table->index(['workspace_id', 'catalog_series_ref']);
            $table->index('catalog_series_ref');
        });

        Schema::table('site_offers', fn (Blueprint $table) => $table->dropIndex(['catalog_equipment_public_id']));
        Schema::table('site_offers', fn (Blueprint $table) => $table->dropColumn('catalog_equipment_public_id'));
        Schema::table('site_offers', function (Blueprint $table) {
            $table->string('catalog_equipment_ref', 64)->default('')->after('site_vehicle_id');
            $table->unsignedBigInteger('price_minor')->nullable()->change();
            $table->index('catalog_equipment_ref');
        });

        Schema::table('series_media_sets', function (Blueprint $table) {
            $table->dropUnique(['catalog_series_public_id', 'name']);
            $table->dropIndex('series_media_sets_series_status_order_index');
        });
        Schema::table('series_media_sets', fn (Blueprint $table) => $table->dropColumn('catalog_series_public_id'));
        Schema::table('series_media_sets', function (Blueprint $table) {
            $table->string('catalog_series_ref', 48)->default('')->after('public_id');
            $table->unique(['catalog_series_ref', 'name']);
            $table->index(['catalog_series_ref', 'status', 'sort_order'], 'series_media_sets_series_status_order_index');
        });

        Schema::create('series_gallery_images', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->string('catalog_series_ref', 48);
            $table->string('path')->unique();
            $table->string('original_name', 255);
            $table->string('mime_type', 100);
            $table->unsignedBigInteger('size_bytes');
            $table->unsignedInteger('width');
            $table->unsignedInteger('height');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['catalog_series_ref', 'sort_order']);
        });

        Schema::create('vehicle_colors', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('workspace_vehicle_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('site_vehicle_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->char('swatch_hex', 7)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        // Rows are never hard-deleted by the application: Published Versions keep serving their
        // photos after a vehicle or photo is removed.
        Schema::create('vehicle_photos', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('workspace_vehicle_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('site_vehicle_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('vehicle_color_id')->nullable()->constrained()->nullOnDelete();
            $table->string('angle', 16)->nullable();
            $table->string('source', 16);
            $table->string('path')->unique();
            $table->string('original_name', 255);
            $table->string('mime_type', 100);
            $table->unsignedBigInteger('size_bytes');
            $table->unsignedInteger('width');
            $table->unsignedInteger('height');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('workspace_vehicle_offers', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('workspace_vehicle_id')->constrained()->cascadeOnDelete();
            $table->string('catalog_equipment_ref', 64);
            $table->unsignedBigInteger('price_minor')->nullable();
            $table->unsignedBigInteger('rrp_minor')->nullable();
            $table->char('currency', 3);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['workspace_vehicle_id', 'catalog_equipment_ref'], 'workspace_vehicle_offers_vehicle_equipment_unique');
        });

        Schema::create('vehicle_collections', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['workspace_id', 'name']);
        });

        Schema::create('vehicle_collection_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vehicle_collection_id')->constrained()->cascadeOnDelete();
            $table->foreignId('workspace_vehicle_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['vehicle_collection_id', 'workspace_vehicle_id'], 'vehicle_collection_items_unique');
        });
    }

    public function down(): void
    {
        throw new RuntimeException('The switch to the auto.ru catalog is not reversible.');
    }

    private function removeLegacyRows(): void
    {
        $existing = [];

        foreach (self::LEGACY_TABLES as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            if (DB::table($table)->exists()) {
                $existing[] = $table;
            }
        }

        if ($existing === []) {
            return;
        }

        if (app()->isProduction()) {
            throw new RuntimeException('Catalog V2 vehicle rows exist ('.implode(', ', $existing).'); migrate them manually before switching to the auto.ru catalog.');
        }

        foreach (self::LEGACY_TABLES as $table) {
            if (Schema::hasTable($table)) {
                DB::table($table)->delete();
            }
        }
    }
};
