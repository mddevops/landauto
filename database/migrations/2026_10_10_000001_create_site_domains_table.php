<?php

use App\Enums\DomainRoutingStatus;
use App\Enums\DomainSslStatus;
use App\Enums\DomainVerificationStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Custom hostnames of a Site (P7-001). Hostnames are stored normalized (lowercase, no trailing
     * dot), so the unique index is case-insensitive in effect. Certificates and keys are never
     * stored; SSL columns are lifecycle metadata only.
     */
    public function up(): void
    {
        Schema::create('site_domains', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('site_id')->constrained()->cascadeOnDelete();
            $table->string('hostname', 253)->unique();
            $table->string('verification_token', 64);
            $table->string('verification_status', 32)->default(DomainVerificationStatus::Pending->value);
            $table->string('routing_status', 32)->default(DomainRoutingStatus::Pending->value);
            $table->string('ssl_status', 32)->default(DomainSslStatus::Pending->value);
            $table->boolean('is_primary')->default(false);
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('routing_verified_at')->nullable();
            $table->timestamp('ssl_issued_at')->nullable();
            $table->timestamp('ssl_expires_at')->nullable();
            $table->timestamp('last_checked_at')->nullable();
            $table->string('last_error_code', 64)->nullable();
            $table->string('last_error_message_safe', 255)->nullable();
            $table->timestamps();

            $table->index(['site_id', 'is_primary']);
            $table->index(['verification_status', 'last_checked_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_domains');
    }
};
