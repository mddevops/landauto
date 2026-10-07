<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('templates', function (Blueprint $table) {
            // Customer catalog access (D-079), as for Block Definitions; existing Templates stay free.
            $table->string('access_mode', 16)->default('free')->after('site_types');
            $table->string('access_entitlement', 64)->nullable()->after('access_mode');
            $table->unsignedBigInteger('price_minor')->nullable()->after('access_entitlement');
            $table->char('price_currency', 3)->nullable()->after('price_minor');
        });

        // A license covers exactly one catalog item: a Block Definition or a Template (D-079).
        Schema::table('site_licenses', function (Blueprint $table) {
            $table->foreignId('block_definition_id')->nullable()->change();
            $table->foreignId('template_id')->nullable()->after('block_definition_id')->constrained()->cascadeOnDelete();

            $table->unique(['site_id', 'template_id']);
        });
    }

    public function down(): void
    {
        Schema::table('site_licenses', function (Blueprint $table) {
            $table->dropUnique(['site_id', 'template_id']);
            $table->dropConstrainedForeignId('template_id');
        });

        Schema::table('templates', function (Blueprint $table) {
            $table->dropColumn(['access_mode', 'access_entitlement', 'price_minor', 'price_currency']);
        });
    }
};
