<?php

namespace App\Blocks;

use App\Models\BlockVersion;
use App\Models\Site;
use Illuminate\Support\Facades\DB;

/**
 * Installed Block Version grandfathering (D-122): a Site that lawfully installed a pinned Block
 * Version keeps using exactly that version, whatever the author later changes about access,
 * prices or visibility, and whether a license is revoked. Grants are written only by trusted
 * installation paths after the current catalog check passed (adding / duplicating a Block,
 * installing a Template) or by restoring the Site's own historical version; never from the
 * browser, never by publishing and never for another Site.
 */
final class BlockVersionGrants
{
    private const TABLE = 'site_block_version_grants';

    /**
     * @param  iterable<int>  $versionIds
     */
    public function grant(Site $site, iterable $versionIds): void
    {
        $now = now();
        $rows = [];

        foreach ($versionIds as $versionId) {
            $rows[$versionId] = ['site_id' => $site->id, 'block_version_id' => $versionId, 'created_at' => $now];
        }

        if ($rows !== []) {
            DB::table(self::TABLE)->insertOrIgnore(array_values($rows));
        }
    }

    public function has(Site $site, BlockVersion $version): bool
    {
        return DB::table(self::TABLE)->where('site_id', $site->id)->where('block_version_id', $version->id)->exists();
    }

    /**
     * @return array<int, true>
     */
    public function grantedVersionIds(Site $site): array
    {
        $ids = DB::table(self::TABLE)->where('site_id', $site->id)->pluck('block_version_id')->all();

        return array_fill_keys(array_map(intval(...), $ids), true);
    }
}
