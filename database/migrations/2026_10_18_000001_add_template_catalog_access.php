<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('templates', function (Blueprint $table) {
            // Customer catalog access (D-121), as for Block Definitions; existing Templates stay free.
            // Template licenses live in `catalog_licenses` (template_id).
            $table->string('access_mode', 16)->default('free')->after('site_types');
            $table->string('access_entitlement', 64)->nullable()->after('access_mode');
            $table->unsignedBigInteger('site_price_minor')->nullable()->after('access_entitlement');
            $table->unsignedBigInteger('workspace_price_minor')->nullable()->after('site_price_minor');
            $table->char('price_currency', 3)->nullable()->after('workspace_price_minor');
        });
    }

    public function down(): void
    {
        Schema::table('templates', function (Blueprint $table) {
            $table->dropColumn(['access_mode', 'access_entitlement', 'site_price_minor', 'workspace_price_minor', 'price_currency']);
        });
    }
};
