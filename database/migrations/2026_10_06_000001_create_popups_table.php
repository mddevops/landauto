<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('popups', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('site_id')->constrained()->restrictOnDelete();
            $table->string('name', 120);
            $table->boolean('status')->default(true);
            $table->string('title', 160)->nullable();
            $table->text('text')->nullable();
            $table->string('size', 16)->default('medium');
            $table->string('animation', 16)->default('fade');
            $table->boolean('close_on_overlay')->default(true);
            $table->boolean('close_on_escape')->default(true);
            $table->boolean('show_close_button')->default(true);
            $table->boolean('mobile_fullscreen')->default(false);
            $table->timestamps();

            $table->index(['site_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('popups');
    }
};
