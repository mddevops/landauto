<?php

use App\Models\Site;
use App\Support\SiteSubdomain;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Site::query()->whereNull('subdomain')->orderBy('id')->each(function (Site $site): void {
            $site->forceFill(['subdomain' => SiteSubdomain::suggest($site->name)])->saveQuietly();
        });
    }

    public function down(): void
    {
        // Assigned labels are kept: they may already be public addresses.
    }
};
