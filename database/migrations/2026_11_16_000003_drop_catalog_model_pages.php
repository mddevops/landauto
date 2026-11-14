<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * D-131: the catalog section has no model level. Model template Pages are removed and vehicle
 * addresses are rebuilt as `model-generation` on the next read; old addresses are not redirected.
 */
return new class extends Migration
{
    public function up(): void
    {
        $pages = DB::table('pages')->where('section_role', 'model')->pluck('id');

        DB::table('page_blocks')->whereIn('page_id', $pages)->delete();
        DB::table('pages')->whereIn('id', $pages)->delete();

        DB::table('site_vehicle_slug_redirects')->delete();
        DB::table('site_vehicles')->update(['slug' => null]);
    }

    public function down(): void
    {
        // Removed model Pages and previous addresses are not restored.
    }
};
