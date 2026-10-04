<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('published_versions', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('site_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('version_number');
            $table->string('status', 16)->default('building');
            $table->json('public_manifest_json');
            $table->json('draft_snapshot_json');
            $table->char('manifest_hash', 64);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('ready_at')->nullable();
            $table->timestamps();

            $table->unique(['site_id', 'version_number']);
            $table->index(['site_id', 'status']);
        });

        Schema::create('published_pages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('published_version_id')->constrained()->cascadeOnDelete();
            $table->char('page_public_id', 26);
            $table->string('slug');
            $table->boolean('is_home')->default(false);
            $table->string('title', 255);
            $table->unsignedInteger('sort_order')->default(0);
            $table->longText('rendered_html');
            $table->json('hydration_json');
            $table->json('seo_json');
            $table->char('content_hash', 64);
            $table->timestamps();

            $table->unique(['published_version_id', 'page_public_id']);
            $table->unique(['published_version_id', 'slug']);
        });

        Schema::create('published_asset_references', function (Blueprint $table) {
            $table->id();
            $table->foreignId('published_version_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 32);
            $table->char('reference_public_id', 26);

            $table->unique(['published_version_id', 'kind', 'reference_public_id'], 'published_asset_refs_unique');
            $table->index(['kind', 'reference_public_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('published_asset_references');
        Schema::dropIfExists('published_pages');
        Schema::dropIfExists('published_versions');
    }
};
