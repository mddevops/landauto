<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The stock section for new vehicles is named «Новые». Only the default title is rewritten;
 * a title the customer already changed stays as it is.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('site_sections')
            ->where('type', 'specials')
            ->whereIn('title', ['Спецпредложения', 'Спецпредложение'])
            ->update(['title' => 'Новые']);
    }

    public function down(): void
    {
        DB::table('site_sections')
            ->where('type', 'specials')
            ->where('title', 'Новые')
            ->update(['title' => 'Спецпредложения']);
    }
};
