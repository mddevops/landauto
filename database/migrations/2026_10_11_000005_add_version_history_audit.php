<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('publications', function (Blueprint $table) {
            $table->string('note', 500)->nullable()->after('actor_user_id');
        });

        Schema::create('site_version_restores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_id')->constrained()->restrictOnDelete();
            $table->foreignId('published_version_id')->constrained()->restrictOnDelete();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('restored_at');
            $table->timestamps();

            $table->index(['site_id', 'id']);
            $table->index(['published_version_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_version_restores');

        Schema::table('publications', function (Blueprint $table) {
            $table->dropColumn('note');
        });
    }
};
