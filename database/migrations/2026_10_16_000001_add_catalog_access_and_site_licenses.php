<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('block_definitions', function (Blueprint $table) {
            // Customer catalog access (D-079); existing Blocks stay free.
            $table->string('access_mode', 16)->default('free')->after('category');
            $table->string('access_entitlement', 64)->nullable()->after('access_mode');
            // ADR-004: integer minor units + ISO 4217 currency, only for `paid`.
            $table->unsignedBigInteger('price_minor')->nullable()->after('access_entitlement');
            $table->char('price_currency', 3)->nullable()->after('price_minor');
        });

        // One license = one catalog item × one Site (D-079).
        Schema::create('site_licenses', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('site_id')->constrained()->cascadeOnDelete();
            $table->foreignId('block_definition_id')->constrained()->cascadeOnDelete();
            $table->string('source', 16);
            $table->foreignId('granted_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['site_id', 'block_definition_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_licenses');

        Schema::table('block_definitions', function (Blueprint $table) {
            $table->dropColumn(['access_mode', 'access_entitlement', 'price_minor', 'price_currency']);
        });
    }
};
