<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Mirror of the auto.ru catalog schema owned by the catalog team (ADR-010), for tests, E2E and
 * empty local databases. A database that already holds the auto.ru tables is left untouched.
 */
return new class extends Migration
{
    protected $connection = 'catalog';

    public function up(): void
    {
        $schema = Schema::connection($this->connection);

        if ($schema->hasTable('auto_marks')) {
            return;
        }

        $schema->create('auto_marks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('external_id')->nullable()->unique();
            $table->string('external_code', 64)->nullable();
            $table->string('name');
            $table->string('name_ru')->nullable();
            $table->string('url')->unique();
            $table->string('logo_min')->nullable();
            $table->string('logo_big')->nullable();
            $table->string('logo_dark')->nullable();
            $table->string('country')->nullable();
            $table->tinyInteger('popular')->nullable();
            $table->tinyInteger('status')->nullable();
            $table->unsignedInteger('ordering')->nullable()->index();
            $table->dateTime('checked_at')->nullable();
        });

        $schema->create('auto_models', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mark_id')->constrained('auto_marks')->cascadeOnDelete();
            $table->unsignedBigInteger('external_id')->nullable()->index();
            $table->string('external_code', 64)->nullable();
            $table->string('name');
            $table->string('name_ru')->nullable();
            $table->string('url');
            $table->boolean('is_custom')->default(false);
            $table->string('class')->nullable();
            $table->unsignedSmallInteger('year_from')->nullable();
            $table->unsignedSmallInteger('year_to')->nullable();
            $table->foreignId('parent_id')->nullable()->constrained('auto_models')->nullOnDelete();
            $table->tinyInteger('status')->nullable();
            $table->unsignedInteger('ordering')->nullable();
            $table->dateTime('checked_at')->nullable();
            $table->unique(['mark_id', 'url']);
        });

        $schema->create('auto_generations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('model_id')->constrained('auto_models')->cascadeOnDelete();
            $table->unsignedBigInteger('external_id')->nullable();
            $table->string('name');
            $table->unsignedSmallInteger('year_from')->nullable();
            $table->unsignedSmallInteger('year_to')->nullable();
            $table->index(['model_id', 'external_id']);
        });

        $schema->create('auto_series', function (Blueprint $table) {
            $table->id();
            $table->foreignId('model_id')->constrained('auto_models')->cascadeOnDelete();
            $table->foreignId('generation_id')->constrained('auto_generations')->cascadeOnDelete();
            $table->unsignedBigInteger('external_id')->nullable()->index();
            $table->string('name');
            $table->string('url');
            $table->string('image')->nullable();
            $table->tinyInteger('status')->nullable();
            $table->dateTime('checked_at')->nullable();
        });

        $schema->create('auto_modifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('series_id')->constrained('auto_series')->cascadeOnDelete();
            $table->unsignedBigInteger('external_id')->default(0)->index();
            $table->string('name');
            $table->decimal('engine_volume', 3, 1)->nullable();
            $table->unsignedSmallInteger('engine_power')->nullable();
            $table->string('engine')->nullable();
            $table->string('transmission')->nullable();
            $table->string('drive')->nullable();
            $table->decimal('consumption_100_km', 4, 1)->nullable();
            $table->decimal('consumption_city', 4, 1)->nullable();
            $table->decimal('consumption_highway', 4, 1)->nullable();
            $table->decimal('acceleration_0_100', 4, 1)->nullable();
            $table->boolean('status')->default(true);
            $table->dateTime('specs_parsed_at')->nullable();
            $table->unique(['series_id', 'external_id']);
        });

        $schema->create('auto_equipments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('series_id')->constrained('auto_series')->cascadeOnDelete();
            $table->foreignId('modification_id')->constrained('auto_modifications')->cascadeOnDelete();
            $table->unsignedBigInteger('external_id')->default(0);
            $table->string('external_key', 64)->default('')->index();
            $table->string('name');
            $table->boolean('status')->default(true);
            $table->string('url')->nullable();
            $table->dateTime('options_parsed_at')->nullable();
            $table->unique(['series_id', 'external_key']);
            $table->index(['series_id', 'external_id']);
        });

        foreach (['auto_characteristics', 'auto_options'] as $dictionary) {
            $schema->create($dictionary, function (Blueprint $table) use ($dictionary) {
                $table->id();
                $table->string('name');
                $table->foreignId('parent_id')->nullable()->constrained($dictionary)->nullOnDelete();
                $table->unsignedInteger('sort')->default(0);
                $table->unique(['parent_id', 'name']);
            });
        }

        $schema->create('auto_characteristic_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('modification_id')->constrained('auto_modifications')->cascadeOnDelete();
            $table->foreignId('characteristic_id')->constrained('auto_characteristics')->cascadeOnDelete();
            $table->string('value')->nullable();
            $table->string('unit')->nullable();
            $table->unique(['modification_id', 'characteristic_id'], 'auto_characteristic_values_unique');
        });

        $schema->create('auto_option_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('option_id')->constrained('auto_options')->cascadeOnDelete();
            $table->foreignId('equipment_id')->constrained('auto_equipments')->cascadeOnDelete();
            $table->boolean('is_base')->default(false);
            $table->unique(['option_id', 'equipment_id']);
        });
    }

    public function down(): void
    {
        // The auto.ru schema belongs to the catalog team; it is never dropped by Landflow.
    }
};
