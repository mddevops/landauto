<?php

namespace App\Platform;

use App\Enums\Entitlement;
use App\Models\Plan;
use App\Models\PlatformSetting;
use App\Support\DefaultWorkspacePlan;

/**
 * Durable Super Admin defaults for launch policies (D-094, X-017).
 * Missing rows fall back to the approved product defaults.
 */
final class PlatformSettings
{
    public const SUBMISSION_RETENTION_MONTHS = 'submission_retention_months';

    public const ACCOUNT_DELETION_GRACE_DAYS = 'account_deletion_grace_days';

    public const FREE_MAX_STORAGE_MB = 'free_max_storage_mb';

    public const DEFAULT_SUBMISSION_RETENTION_MONTHS = 12;

    public const DEFAULT_ACCOUNT_DELETION_GRACE_DAYS = 30;

    public const DEFAULT_FREE_MAX_STORAGE_MB = 500;

    public function submissionRetentionMonths(): int
    {
        return max(1, (int) $this->get(self::SUBMISSION_RETENTION_MONTHS, self::DEFAULT_SUBMISSION_RETENTION_MONTHS));
    }

    public function accountDeletionGraceDays(): int
    {
        return max(1, (int) $this->get(self::ACCOUNT_DELETION_GRACE_DAYS, self::DEFAULT_ACCOUNT_DELETION_GRACE_DAYS));
    }

    public function freeMaxStorageMb(): int
    {
        return max(1, (int) $this->get(self::FREE_MAX_STORAGE_MB, self::DEFAULT_FREE_MAX_STORAGE_MB));
    }

    /**
     * @return array{submission_retention_months: int, account_deletion_grace_days: int, free_max_storage_mb: int}
     */
    public function all(): array
    {
        return [
            self::SUBMISSION_RETENTION_MONTHS => $this->submissionRetentionMonths(),
            self::ACCOUNT_DELETION_GRACE_DAYS => $this->accountDeletionGraceDays(),
            self::FREE_MAX_STORAGE_MB => $this->freeMaxStorageMb(),
        ];
    }

    /**
     * @param  array{submission_retention_months?: int, account_deletion_grace_days?: int, free_max_storage_mb?: int}  $values
     */
    public function update(array $values): void
    {
        if (isset($values[self::SUBMISSION_RETENTION_MONTHS])) {
            $this->put(self::SUBMISSION_RETENTION_MONTHS, max(1, (int) $values[self::SUBMISSION_RETENTION_MONTHS]));
        }

        if (isset($values[self::ACCOUNT_DELETION_GRACE_DAYS])) {
            $this->put(self::ACCOUNT_DELETION_GRACE_DAYS, max(1, (int) $values[self::ACCOUNT_DELETION_GRACE_DAYS]));
        }

        if (isset($values[self::FREE_MAX_STORAGE_MB])) {
            $mb = max(1, (int) $values[self::FREE_MAX_STORAGE_MB]);
            $this->put(self::FREE_MAX_STORAGE_MB, $mb);
            $this->syncFreeStorageEntitlement($mb);
        }
    }

    public function ensureFreeStorageEntitlement(): void
    {
        $this->syncFreeStorageEntitlement($this->freeMaxStorageMb());
    }

    private function syncFreeStorageEntitlement(int $mb): void
    {
        $plan = Plan::query()->where('key', Plan::FREE_KEY)->first()
            ?? app(DefaultWorkspacePlan::class)->resolve();

        $plan->setEntitlement(Entitlement::MaxStorageMb, $mb);
    }

    private function get(string $key, int $default): int
    {
        $row = PlatformSetting::query()->find($key);

        return is_numeric($row?->value) ? (int) $row->value : $default;
    }

    private function put(string $key, int $value): void
    {
        PlatformSetting::query()->updateOrCreate(['key' => $key], ['value' => $value]);
    }
}
