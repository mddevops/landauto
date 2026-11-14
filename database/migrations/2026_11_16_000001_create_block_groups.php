<?php

use App\Blocks\BlockGroups;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('block_groups', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->string('name', 80)->unique();
            $table->string('kind', 32)->default('other');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::table('block_definitions', function (Blueprint $table) {
            $table->foreignId('block_group_id')->nullable()->after('category')->constrained()->restrictOnDelete();
        });

        app(BlockGroups::class)->backfill();
    }

    public function down(): void
    {
        Schema::table('block_definitions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('block_group_id');
        });

        Schema::dropIfExists('block_groups');
    }
};
