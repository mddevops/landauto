<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('block_definitions', function (Blueprint $table) {
            // Customer catalog access (D-121); existing Blocks stay free.
            $table->string('access_mode', 16)->default('free')->after('category');
            $table->string('access_entitlement', 64)->nullable()->after('access_mode');
            // ADR-004: integer minor units + ISO 4217 currency, only for `paid`; each license option is optional.
            $table->unsignedBigInteger('site_price_minor')->nullable()->after('access_entitlement');
            $table->unsignedBigInteger('workspace_price_minor')->nullable()->after('site_price_minor');
            $table->char('price_currency', 3)->nullable()->after('workspace_price_minor');
        });

        // One license = one catalog item (Block or Template) × one target (Site or Workspace) (D-121).
        Schema::create('catalog_licenses', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->string('scope', 16);
            $table->foreignId('site_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('workspace_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('block_definition_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('template_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('source', 16);
            $table->foreignId('granted_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['site_id', 'block_definition_id']);
            $table->unique(['site_id', 'template_id']);
            $table->unique(['workspace_id', 'block_definition_id']);
            $table->unique(['workspace_id', 'template_id']);
        });

        // Installed Block Version grandfathering (D-122): internal provenance, never browser-controlled.
        Schema::create('site_block_version_grants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_id')->constrained()->cascadeOnDelete();
            $table->foreignId('block_version_id')->constrained()->restrictOnDelete();
            $table->timestamp('created_at')->nullable();

            $table->unique(['site_id', 'block_version_id']);
        });

        // Every Block Version already placed on a Site was installed lawfully under the rules of its time.
        DB::table('site_block_version_grants')->insertUsing(
            ['site_id', 'block_version_id', 'created_at'],
            DB::table('page_blocks')
                ->join('pages', 'pages.id', '=', 'page_blocks.page_id')
                ->select(['pages.site_id', 'page_blocks.block_version_id'])
                ->selectRaw('? as created_at', [now()])
                ->distinct(),
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('site_block_version_grants');
        Schema::dropIfExists('catalog_licenses');

        Schema::table('block_definitions', function (Blueprint $table) {
            $table->dropColumn(['access_mode', 'access_entitlement', 'site_price_minor', 'workspace_price_minor', 'price_currency']);
        });
    }
};
