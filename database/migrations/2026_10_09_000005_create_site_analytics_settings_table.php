<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('site_analytics_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_id')->unique()->constrained()->cascadeOnDelete();
            $table->boolean('yandex_metrica_enabled')->default(false);
            $table->string('yandex_metrica_counter_id', 16)->nullable();
            $table->boolean('clickmap')->default(true);
            $table->boolean('track_links')->default(true);
            $table->boolean('accurate_track_bounce')->default(true);
            $table->boolean('webvisor_enabled')->default(false);
            $table->json('event_tracking_json')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_analytics_settings');
    }
};
