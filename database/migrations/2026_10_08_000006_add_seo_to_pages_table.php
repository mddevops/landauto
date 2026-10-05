<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pages', function (Blueprint $table) {
            $table->string('seo_title', 120)->nullable()->after('slug');
            $table->string('seo_description', 300)->nullable()->after('seo_title');
            $table->boolean('seo_noindex')->default(false)->after('seo_description');
        });
    }

    public function down(): void
    {
        Schema::table('pages', function (Blueprint $table) {
            $table->dropColumn(['seo_title', 'seo_description', 'seo_noindex']);
        });
    }
};
