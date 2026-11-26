<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('popups', function (Blueprint $table) {
            $table->json('launch_json')->nullable()->after('mobile_fullscreen');
        });

        Schema::table('sites', function (Blueprint $table) {
            $table->json('floating_launcher_json')->nullable()->after('design_tokens');
        });
    }

    public function down(): void
    {
        Schema::table('popups', function (Blueprint $table) {
            $table->dropColumn('launch_json');
        });

        Schema::table('sites', function (Blueprint $table) {
            $table->dropColumn('floating_launcher_json');
        });
    }
};
