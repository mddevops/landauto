<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pages', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            // Site deletion requires an explicit domain workflow; Pages never disappear implicitly.
            $table->foreignId('site_id')->constrained()->restrictOnDelete();
            $table->string('title');
            $table->string('slug');
            $table->unsignedInteger('sort_order')->default(0);
            // TRUE for the home Page, NULL otherwise: the unique index allows one home Page per Site.
            $table->boolean('is_home')->nullable();
            $table->timestamps();

            $table->unique(['site_id', 'slug']);
            $table->unique(['site_id', 'is_home']);
            $table->index(['site_id', 'sort_order']);
        });

        $now = now();

        DB::table('sites')
            ->select('id')
            ->orderBy('id')
            ->chunkById(500, function ($sites) use ($now): void {
                DB::table('pages')->insert($sites->map(fn (object $site): array => [
                    'public_id' => (string) Str::ulid(),
                    'site_id' => $site->id,
                    'title' => 'Главная',
                    'slug' => 'home',
                    'sort_order' => 0,
                    'is_home' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ])->all());
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('pages');
    }
};
