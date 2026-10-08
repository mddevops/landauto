<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Explicit Developer creator permissions (D-118). Existing profiles receive the current defaults
 * that new Super Admin-granted profiles get, so no profile silently loses its approved access.
 */
return new class extends Migration
{
    /** Literal keys: the migration must not change if the enum grows later. */
    private const DEFAULT_PERMISSIONS = ['create_blocks', 'create_templates', 'submit_marketplace_item'];

    public function up(): void
    {
        Schema::create('developer_profile_permissions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('developer_profile_id')->constrained()->cascadeOnDelete();
            $table->string('permission', 48);
            $table->timestamps();

            $table->unique(['developer_profile_id', 'permission'], 'developer_profile_permissions_unique');
        });

        $now = now();

        DB::table('developer_profiles')
            ->select('id')
            ->orderBy('id')
            ->chunkById(500, function ($profiles) use ($now): void {
                $rows = [];

                foreach ($profiles as $profile) {
                    foreach (self::DEFAULT_PERMISSIONS as $permission) {
                        $rows[] = [
                            'developer_profile_id' => $profile->id,
                            'permission' => $permission,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ];
                    }
                }

                DB::table('developer_profile_permissions')->insertOrIgnore($rows);
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('developer_profile_permissions');
    }
};
