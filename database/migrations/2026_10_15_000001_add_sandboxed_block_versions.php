<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('block_versions', function (Blueprint $table) {
            // Existing versions are rendered by the official application registry.
            $table->string('runtime', 16)->default('official')->after('version');
            // Immutable source snapshot of a sandboxed version (ADR-008); null for official versions.
            $table->mediumText('html')->nullable()->after('schema_json');
            $table->mediumText('css')->nullable()->after('html');
            $table->mediumText('js')->nullable()->after('css');
            $table->foreignId('published_by_user_id')->nullable()->after('js')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('block_versions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('published_by_user_id');
            $table->dropColumn(['runtime', 'html', 'css', 'js']);
        });
    }
};
