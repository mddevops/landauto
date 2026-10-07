<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Template Builder (P9-007): explicit Template ownership, Draft content (Pages + Block Instances of
 * published catalog Blocks) and immutable Template Version snapshots.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('templates', function (Blueprint $table) {
            $table->string('owner_scope', 16)->default('platform')->after('slug');
            $table->foreignId('developer_profile_id')->nullable()->after('owner_scope')->constrained()->restrictOnDelete();
            $table->foreignId('created_by_user_id')->nullable()->after('is_official')->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by_user_id')->nullable()->after('created_by_user_id')->constrained('users')->nullOnDelete();
        });

        Schema::table('template_versions', function (Blueprint $table) {
            $table->json('content_json')->nullable()->after('version');
            $table->foreignId('published_by_user_id')->nullable()->after('content_json')->constrained('users')->nullOnDelete();
        });

        Schema::create('template_pages', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('template_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('slug');
            $table->unsignedInteger('sort_order')->default(0);
            // TRUE for the home Page, NULL otherwise: one home Page per Template.
            $table->boolean('is_home')->nullable();
            $table->timestamps();

            $table->unique(['template_id', 'slug']);
            $table->unique(['template_id', 'is_home']);
        });

        Schema::create('template_blocks', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('template_page_id')->constrained()->cascadeOnDelete();
            $table->foreignId('block_version_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_hidden')->default(false);
            $table->json('state_json');
            $table->timestamps();

            $table->index(['template_page_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('template_blocks');
        Schema::dropIfExists('template_pages');

        Schema::table('template_versions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('published_by_user_id');
            $table->dropColumn('content_json');
        });

        Schema::table('templates', function (Blueprint $table) {
            $table->dropConstrainedForeignId('updated_by_user_id');
            $table->dropConstrainedForeignId('created_by_user_id');
            $table->dropConstrainedForeignId('developer_profile_id');
            $table->dropColumn('owner_scope');
        });
    }
};
