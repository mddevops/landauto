<?php

namespace App\Forms;

use App\Enums\BlacklistScope;
use App\Enums\BlacklistType;
use App\Models\BlacklistEntry;
use App\Models\Site;
use App\Support\PhoneNormalizer;
use Illuminate\Database\Eloquent\Builder;

/**
 * Blacklist normalization and resolution: an active Global, Workspace or Site entry for the
 * submitting IP or normalized phone blocks the Submission. Expired entries are ignored.
 */
class Blacklist
{
    public function __construct(private PhoneNormalizer $phones) {}

    public function normalize(BlacklistType $type, string $value): ?string
    {
        $value = trim($value);

        if ($type === BlacklistType::Phone) {
            return $this->phones->normalize($value);
        }

        $binary = filter_var($value, FILTER_VALIDATE_IP) !== false ? inet_pton($value) : false;

        return $binary === false ? null : (inet_ntop($binary) ?: null);
    }

    public function blocks(Site $site, ?string $ip, ?string $phone): bool
    {
        $ip = $ip !== null ? $this->normalize(BlacklistType::Ip, $ip) : null;
        $values = array_filter([BlacklistType::Ip->value => $ip, BlacklistType::Phone->value => $phone]);

        if ($values === []) {
            return false;
        }

        return BlacklistEntry::query()
            ->active()
            ->where(function (Builder $query) use ($values): void {
                foreach ($values as $type => $value) {
                    $query->orWhere(fn (Builder $query) => $query->where('type', $type)->where('value', $value));
                }
            })
            ->where(fn (Builder $query) => $query
                ->where('scope', BlacklistScope::Global->value)
                ->orWhere(fn (Builder $query) => $query->where('scope', BlacklistScope::Workspace->value)->where('workspace_id', $site->workspace_id))
                ->orWhere(fn (Builder $query) => $query->where('scope', BlacklistScope::Site->value)->where('site_id', $site->id)))
            ->exists();
    }
}
