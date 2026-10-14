<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Marketing / distribution metadata of one canonical product (P10-001). Access mode, prices and
        // licenses stay on the product (D-121); there is no moderation state (D-120).
        Schema::create('marketplace_listings', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->string('product_type', 16);
            // Exactly one product (enforced by the model); unique = at most one listing per product.
            $table->foreignId('block_definition_id')->nullable()->unique()->constrained()->restrictOnDelete();
            $table->foreignId('template_id')->nullable()->unique()->constrained()->restrictOnDelete();
            // Derived from the product owner; null for official Landflow products (D-117).
            $table->foreignId('developer_profile_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('title', 120);
            $table->string('slug', 80)->unique();
            $table->text('description')->nullable();
            $table->string('status', 16)->default('draft');
            $table->timestamp('published_at')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketplace_listings');
    }
};
