<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('name');
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('plan_entitlements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plan_id')->constrained()->cascadeOnDelete();
            $table->string('key', 64);
            $table->string('value_type', 16);
            $table->boolean('boolean_value')->nullable();
            $table->unsignedInteger('integer_value')->nullable();
            $table->timestamps();

            $table->unique(['plan_id', 'key']);
        });

        Schema::table('workspaces', function (Blueprint $table) {
            $table->foreignId('plan_id')
                ->nullable()
                ->after('public_id')
                ->constrained()
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('workspaces', function (Blueprint $table) {
            $table->dropConstrainedForeignId('plan_id');
        });

        Schema::dropIfExists('plan_entitlements');
        Schema::dropIfExists('plans');
    }
};
