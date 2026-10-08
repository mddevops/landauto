<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Explicit Block Definition ownership (D-117) replaces `is_official`. Every existing definition is
 * an official Landflow Block, so all of them become platform-owned. Creator / editor Users are
 * audit identity only and never own a Block.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('block_definitions', function (Blueprint $table): void {
            $table->string('owner_scope', 32)->nullable()->after('slug');
            $table->foreignId('developer_profile_id')->nullable()->after('owner_scope')->constrained()->restrictOnDelete();
            $table->foreignId('workspace_id')->nullable()->after('developer_profile_id')->constrained()->restrictOnDelete();
            $table->foreignId('created_by_user_id')->nullable()->after('workspace_id')->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by_user_id')->nullable()->after('created_by_user_id')->constrained('users')->nullOnDelete();
        });

        DB::table('block_definitions')->update(['owner_scope' => 'platform']);

        Schema::table('block_definitions', function (Blueprint $table): void {
            $table->string('owner_scope', 32)->nullable(false)->change();
            $table->index(['owner_scope', 'developer_profile_id']);
            $table->dropColumn('is_official');
        });
    }

    public function down(): void
    {
        Schema::table('block_definitions', function (Blueprint $table): void {
            $table->boolean('is_official')->default(false)->after('slug');
        });

        DB::table('block_definitions')->where('owner_scope', 'platform')->update(['is_official' => true]);

        Schema::table('block_definitions', function (Blueprint $table): void {
            $table->dropIndex(['owner_scope', 'developer_profile_id']);
            $table->dropConstrainedForeignId('updated_by_user_id');
            $table->dropConstrainedForeignId('created_by_user_id');
            $table->dropConstrainedForeignId('workspace_id');
            $table->dropConstrainedForeignId('developer_profile_id');
            $table->dropColumn('owner_scope');
        });
    }
};
