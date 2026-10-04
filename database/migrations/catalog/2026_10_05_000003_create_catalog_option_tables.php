<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Catalog V2 factory options: two-level dictionary and Equipment option values.
 * `is_base` intentionally has no default; a missing row means "unknown".
 */
return new class extends Migration
{
    protected $connection = 'catalog';

    public function up(): void
    {
        Schema::create('auto_options', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->string('code', 100)->unique();
            $table->string('name');
            $table->foreignId('parent_id')->nullable()->constrained('auto_options')->restrictOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['parent_id', 'sort_order']);
        });

        Schema::create('auto_option_values', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('option_id')->constrained('auto_options')->restrictOnDelete();
            $table->foreignId('equipment_id')->constrained('auto_equipments')->restrictOnDelete();
            $table->boolean('is_base');
            $table->timestamps();

            $table->unique(['equipment_id', 'option_id']);
            $table->index('option_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('auto_option_values');
        Schema::dropIfExists('auto_options');
    }
};
