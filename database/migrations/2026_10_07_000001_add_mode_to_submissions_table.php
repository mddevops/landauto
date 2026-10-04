<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('submissions', function (Blueprint $table) {
            $table->string('mode', 16)->default('public')->after('status');
            $table->index(['site_id', 'mode', 'submitted_at']);
        });
    }

    public function down(): void
    {
        Schema::table('submissions', function (Blueprint $table) {
            $table->dropIndex(['site_id', 'mode', 'submitted_at']);
            $table->dropColumn('mode');
        });
    }
};
