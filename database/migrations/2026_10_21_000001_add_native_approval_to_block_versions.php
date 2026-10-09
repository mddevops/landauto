<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('block_versions', function (Blueprint $table): void {
            $table->unsignedInteger('approved_revision')->nullable()->after('published_by_user_id');
            $table->char('approved_source_hash', 64)->nullable()->after('approved_revision');
            $table->foreignId('approved_by_user_id')->nullable()->after('approved_source_hash')->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable()->after('approved_by_user_id');
        });
    }

    public function down(): void
    {
        Schema::table('block_versions', function (Blueprint $table): void {
            $table->dropForeign(['approved_by_user_id']);
            $table->dropColumn(['approved_revision', 'approved_source_hash', 'approved_by_user_id', 'approved_at']);
        });
    }
};
