<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('site_integration_bindings', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('site_id')->constrained()->cascadeOnDelete();
            $table->foreignId('integration_profile_id')->constrained()->cascadeOnDelete();
            $table->string('name', 120)->nullable();
            $table->json('overrides_json');
            $table->json('mapping_defaults_json')->nullable();
            $table->string('status', 16)->default('active');
            $table->timestamps();

            $table->index(['site_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_integration_bindings');
    }
};
