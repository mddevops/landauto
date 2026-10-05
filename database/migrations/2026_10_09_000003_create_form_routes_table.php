<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('form_routes', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('form_id')->constrained()->cascadeOnDelete();
            $table->string('name', 120);
            $table->string('destination_type', 32);
            $table->foreignId('integration_profile_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('site_integration_binding_id')->nullable()->constrained()->cascadeOnDelete();
            $table->json('email_destination')->nullable();
            $table->json('mapping_json')->nullable();
            $table->json('settings_json')->nullable();
            $table->string('status', 16)->default('active');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['form_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('form_routes');
    }
};
