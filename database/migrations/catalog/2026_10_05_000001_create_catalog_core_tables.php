<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Catalog V2 core hierarchy (docs/architecture/AUTO_CATALOG_SCHEMA.md) on the separate
 * catalog connection. `public_id` is the additive ADR-001 external identifier.
 */
return new class extends Migration
{
    protected $connection = 'catalog';

    public function up(): void
    {
        Schema::create('auto_marks', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->string('name');
            $table->string('name_ru')->nullable();
            $table->string('url', 160)->unique();
            $table->string('logo_min', 1024)->nullable();
            $table->string('logo_big', 1024)->nullable();
            $table->string('country', 100)->nullable();
            $table->boolean('status')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['status', 'sort_order']);
        });

        Schema::create('auto_models', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('mark_id')->constrained('auto_marks')->restrictOnDelete();
            $table->string('name');
            $table->string('name_ru')->nullable();
            $table->string('url', 160);
            $table->string('class', 32)->nullable();
            $table->unsignedSmallInteger('year_from')->nullable();
            $table->unsignedSmallInteger('year_to')->nullable();
            $table->foreignId('parent_id')->nullable()->constrained('auto_models')->restrictOnDelete();
            $table->boolean('status')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['mark_id', 'url']);
            $table->index(['mark_id', 'status', 'sort_order']);
            $table->index(['parent_id', 'sort_order']);
        });

        Schema::create('auto_generations', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('model_id')->constrained('auto_models')->restrictOnDelete();
            $table->string('name');
            $table->string('url', 160);
            $table->unsignedSmallInteger('year_from')->nullable();
            $table->unsignedSmallInteger('year_to')->nullable();
            $table->boolean('status')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['model_id', 'url']);
            $table->index(['model_id', 'status', 'sort_order']);
        });

        Schema::create('auto_series', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('generation_id')->constrained('auto_generations')->restrictOnDelete();
            $table->string('name');
            $table->string('url', 160);
            $table->string('image', 1024)->nullable();
            $table->boolean('status')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['generation_id', 'url']);
            $table->index(['generation_id', 'status', 'sort_order']);
        });

        Schema::create('auto_modifications', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('series_id')->constrained('auto_series')->restrictOnDelete();
            $table->string('name');
            $table->unsignedInteger('engine_volume')->nullable();
            $table->decimal('engine_power', 8, 2)->unsigned()->nullable();
            $table->string('engine', 32)->nullable();
            $table->string('transmission', 32)->nullable();
            $table->string('drive', 16)->nullable();
            $table->decimal('consumption_100_km', 8, 3)->unsigned()->nullable();
            $table->decimal('acceleration_0_100', 6, 2)->unsigned()->nullable();
            $table->boolean('status')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['series_id', 'status', 'sort_order']);
        });

        Schema::create('auto_equipments', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('modification_id')->constrained('auto_modifications')->restrictOnDelete();
            $table->string('name');
            $table->boolean('status')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['modification_id', 'status', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('auto_equipments');
        Schema::dropIfExists('auto_modifications');
        Schema::dropIfExists('auto_series');
        Schema::dropIfExists('auto_generations');
        Schema::dropIfExists('auto_models');
        Schema::dropIfExists('auto_marks');
    }
};
