<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plan_block_definitions', function (Blueprint $table): void {
            $table->foreignId('plan_id')->constrained()->cascadeOnDelete();
            $table->foreignId('block_definition_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->primary(['plan_id', 'block_definition_id']);
        });

        Schema::create('plan_templates', function (Blueprint $table): void {
            $table->foreignId('plan_id')->constrained()->cascadeOnDelete();
            $table->foreignId('template_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->primary(['plan_id', 'template_id']);
        });

        $now = now();

        DB::table('plan_block_definitions')->insertUsing(
            ['plan_id', 'block_definition_id', 'created_at', 'updated_at'],
            DB::table('block_definitions')
                ->join('plan_entitlements', function ($join): void {
                    $join->on('plan_entitlements.key', '=', 'block_definitions.access_entitlement')
                        ->where('plan_entitlements.value_type', '=', 'boolean')
                        ->where('plan_entitlements.boolean_value', '=', true);
                })
                ->where('block_definitions.access_mode', '=', 'entitlement')
                ->selectRaw('plan_entitlements.plan_id, block_definitions.id, ?, ?', [$now, $now]),
        );

        DB::table('plan_templates')->insertUsing(
            ['plan_id', 'template_id', 'created_at', 'updated_at'],
            DB::table('templates')
                ->join('plan_entitlements', function ($join): void {
                    $join->on('plan_entitlements.key', '=', 'templates.access_entitlement')
                        ->where('plan_entitlements.value_type', '=', 'boolean')
                        ->where('plan_entitlements.boolean_value', '=', true);
                })
                ->where('templates.access_mode', '=', 'entitlement')
                ->selectRaw('plan_entitlements.plan_id, templates.id, ?, ?', [$now, $now]),
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('plan_templates');
        Schema::dropIfExists('plan_block_definitions');
    }
};
