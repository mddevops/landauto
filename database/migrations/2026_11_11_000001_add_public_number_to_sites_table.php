<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sites', function (Blueprint $table) {
            $table->char('public_number', 12)->nullable()->unique()->after('name');
        });

        DB::table('sites')->whereNull('public_number')->orderBy('id')->select('id')->each(function (stdClass $site): void {
            do {
                $number = (string) random_int(100_000_000_000, 999_999_999_999);
            } while (DB::table('sites')->where('public_number', $number)->exists());

            DB::table('sites')->where('id', $site->id)->update(['public_number' => $number]);
        });
    }

    public function down(): void
    {
        Schema::table('sites', function (Blueprint $table) {
            $table->dropUnique(['public_number']);
            $table->dropColumn('public_number');
        });
    }
};
