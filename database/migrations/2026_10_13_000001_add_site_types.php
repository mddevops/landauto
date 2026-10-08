<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * D-119: Site format. Existing Sites could already hold several Pages, so they become `multi_page`;
 * existing Templates declare no compatible type until an author sets one.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sites', function (Blueprint $table) {
            $table->string('site_type', 32)->default('multi_page')->after('name');
        });

        Schema::table('templates', function (Blueprint $table) {
            $table->json('site_types')->nullable()->after('slug');
        });
    }

    public function down(): void
    {
        Schema::table('templates', function (Blueprint $table) {
            $table->dropColumn('site_types');
        });

        Schema::table('sites', function (Blueprint $table) {
            $table->dropColumn('site_type');
        });
    }
};
