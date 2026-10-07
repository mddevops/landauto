<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * D-088 (Phase 8): the Workspace role stays the permission source; Site access only answers
     * whether a member may enter a Site. Existing members keep access to all Sites.
     */
    public function up(): void
    {
        Schema::table('workspace_members', function (Blueprint $table) {
            $table->string('site_access_mode', 32)->default('all_sites')->after('status');
        });

        Schema::table('workspace_invitations', function (Blueprint $table) {
            $table->string('site_access_mode', 32)->default('all_sites')->after('role');
        });

        Schema::create('site_member_access', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_member_id')->constrained()->cascadeOnDelete();
            $table->foreignId('site_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['workspace_member_id', 'site_id']);
            $table->index('site_id');
        });

        Schema::create('workspace_invitation_sites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_invitation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('site_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['workspace_invitation_id', 'site_id'], 'workspace_invitation_sites_unique');
            $table->index('site_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workspace_invitation_sites');
        Schema::dropIfExists('site_member_access');

        Schema::table('workspace_invitations', function (Blueprint $table) {
            $table->dropColumn('site_access_mode');
        });

        Schema::table('workspace_members', function (Blueprint $table) {
            $table->dropColumn('site_access_mode');
        });
    }
};
