<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('forms', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('site_id')->constrained()->restrictOnDelete();
            $table->string('name', 120);
            $table->boolean('status')->default(true);
            $table->string('submit_label', 40);
            $table->string('success_message', 500);
            $table->timestamps();

            $table->index(['site_id', 'status']);
        });

        Schema::create('form_fields', function (Blueprint $table) {
            $table->id();
            $table->foreignId('form_id')->constrained()->cascadeOnDelete();
            $table->string('key', 40);
            $table->string('type', 16);
            $table->string('label', 1000);
            $table->string('placeholder', 120)->nullable();
            $table->string('default_value', 255)->nullable();
            $table->boolean('required')->default(false);
            $table->json('validation')->nullable();
            $table->json('options')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['form_id', 'key']);
        });

        Schema::table('popups', function (Blueprint $table) {
            $table->foreignId('form_id')->nullable()->after('site_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('popups', function (Blueprint $table) {
            $table->dropConstrainedForeignId('form_id');
        });
        Schema::dropIfExists('form_fields');
        Schema::dropIfExists('forms');
    }
};
