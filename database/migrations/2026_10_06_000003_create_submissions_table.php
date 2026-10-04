<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('submissions', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('site_id')->constrained()->restrictOnDelete();
            $table->foreignId('form_id')->constrained()->restrictOnDelete();
            $table->string('status', 24)->default('received');
            $table->json('payload');
            $table->json('context')->nullable();
            $table->string('phone_original', 32)->nullable();
            $table->string('phone_normalized', 15)->nullable();
            $table->string('email_normalized', 254)->nullable();
            $table->string('ip', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->timestamp('submitted_at');
            $table->timestamps();

            $table->index(['site_id', 'submitted_at']);
            $table->index(['form_id', 'phone_normalized', 'submitted_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('submissions');
    }
};
