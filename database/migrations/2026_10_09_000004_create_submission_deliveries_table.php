<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('submission_deliveries', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('submission_id')->constrained()->cascadeOnDelete();
            $table->foreignId('form_route_id')->constrained()->cascadeOnDelete();
            $table->string('destination_type', 32);
            $table->string('status', 32)->default('pending');
            $table->unsignedSmallInteger('attempt_count')->default(0);
            $table->timestamp('next_retry_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('last_attempt_at')->nullable();
            $table->unsignedSmallInteger('last_http_status')->nullable();
            $table->string('last_error_code', 64)->nullable();
            $table->string('last_error_message_safe', 255)->nullable();
            $table->timestamps();

            $table->unique(['submission_id', 'form_route_id']);
            $table->index(['status', 'next_retry_at']);
            $table->index(['form_route_id', 'status']);
        });

        Schema::create('submission_delivery_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('submission_delivery_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('attempt_number');
            $table->string('trigger', 16)->default('automatic');
            $table->timestamp('started_at');
            $table->timestamp('finished_at')->nullable();
            $table->string('status', 32)->nullable();
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->string('provider_code', 64)->nullable();
            $table->unsignedInteger('latency_ms')->nullable();
            $table->json('response_summary_json')->nullable();
            $table->string('error_code', 64)->nullable();
            $table->string('safe_error_message', 255)->nullable();
            $table->timestamps();

            $table->unique(['submission_delivery_id', 'attempt_number'], 'delivery_attempts_number_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('submission_delivery_attempts');
        Schema::dropIfExists('submission_deliveries');
    }
};
