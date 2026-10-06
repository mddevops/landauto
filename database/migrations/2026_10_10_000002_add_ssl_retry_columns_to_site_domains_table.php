<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('site_domains', function (Blueprint $table) {
            $table->unsignedTinyInteger('ssl_attempts')->default(0)->after('ssl_status');
            $table->timestamp('ssl_retry_at')->nullable()->after('ssl_attempts');
        });
    }

    public function down(): void
    {
        Schema::table('site_domains', function (Blueprint $table) {
            $table->dropColumn(['ssl_attempts', 'ssl_retry_at']);
        });
    }
};
