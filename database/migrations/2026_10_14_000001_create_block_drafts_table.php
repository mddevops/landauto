<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Official Block slugs at the time of this migration. */
    private const OFFICIAL_CATEGORIES = [
        'header' => 'menu',
        'hero' => 'hero',
        'benefits' => 'features',
        'cta' => 'cta',
        'contacts' => 'contacts',
        'footer' => 'footer',
        'vehicle-card' => 'vehicles',
        'vehicle-grid' => 'vehicles',
        'vehicle-gallery' => 'vehicles',
        'vehicle-offers' => 'vehicles',
        'vehicle-characteristics' => 'vehicles',
        'vehicle-equipment' => 'vehicles',
    ];

    public function up(): void
    {
        Schema::table('block_definitions', function (Blueprint $table) {
            $table->string('category', 32)->default('other')->after('slug');
        });

        foreach (self::OFFICIAL_CATEGORIES as $slug => $category) {
            DB::table('block_definitions')
                ->where('owner_scope', 'platform')
                ->where('slug', $slug)
                ->update(['category' => $category]);
        }

        Schema::create('block_drafts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('block_definition_id')->unique()->constrained()->cascadeOnDelete();
            $table->mediumText('html');
            $table->mediumText('css');
            $table->mediumText('js');
            $table->mediumText('schema_source');
            $table->json('preview_data')->nullable();
            $table->unsignedInteger('revision')->default(0);
            $table->foreignId('updated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('block_drafts');

        Schema::table('block_definitions', function (Blueprint $table) {
            $table->dropColumn('category');
        });
    }
};
