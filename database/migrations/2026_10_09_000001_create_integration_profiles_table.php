<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('integration_profiles', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->string('name', 120);
            $table->string('provider_type', 32);
            $table->string('provider_key', 64)->nullable();
            $table->string('base_url', 2048)->nullable();
            $table->string('auth_type', 32)->default('none');
            $table->text('encrypted_credentials')->nullable();
            $table->string('credentials_hint', 8)->nullable();
            $table->json('settings_json')->nullable();
            $table->string('status', 16)->default('active');
            $table->timestamps();

            $table->index(['workspace_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('integration_profiles');
    }
};
