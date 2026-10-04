<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Catalog V2 characteristics: two-level dictionary and Equipment values (ADR-005).
 */
return new class extends Migration
{
    protected $connection = 'catalog';

    public function up(): void
    {
        Schema::create('auto_characteristics', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->string('code', 100)->unique();
            $table->string('name');
            $table->foreignId('parent_id')->nullable()->constrained('auto_characteristics')->restrictOnDelete();
            $table->string('unit', 32)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['parent_id', 'sort_order']);
        });

        Schema::create('auto_characteristic_values', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('equipment_id')->constrained('auto_equipments')->restrictOnDelete();
            $table->foreignId('characteristic_id')->constrained('auto_characteristics')->restrictOnDelete();
            $table->text('value');
            $table->timestamps();

            $table->unique(['equipment_id', 'characteristic_id']);
            $table->index('characteristic_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('auto_characteristic_values');
        Schema::dropIfExists('auto_characteristics');
    }
};
