<?php

namespace App\Console\Commands;

use App\Enums\BlacklistScope;
use App\Enums\BlacklistType;
use App\Enums\PlatformRole;
use App\Forms\Blacklist;
use App\Models\BlacklistEntry;
use App\Models\PlatformRoleAssignment;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Operator command for the platform-owned Global blacklist. Every change names a platform
 * super admin and a reason, and is written to the application log for audit. Removal expires
 * the entry instead of deleting it, so the history stays reviewable.
 */
class GlobalBlacklistCommand extends Command
{
    protected $signature = 'blacklist:global
        {action : add, remove or list}
        {--type= : ip or phone (add)}
        {--value= : IP address or phone number (add)}
        {--entry= : Entry public_id (remove)}
        {--reason= : Why the change is made (add, remove)}
        {--actor= : Email of a platform super admin (add, remove)}
        {--days= : Optional expiry in days (add)}
        {--force : Skip the confirmation prompt in production}';

    protected $description = 'Manage Global form blacklist entries (platform operators only)';

    public function handle(Blacklist $blacklist): int
    {
        $action = (string) $this->argument('action');

        if ($action === 'list') {
            $this->table(['public_id', 'type', 'value', 'reason', 'expires_at'], BlacklistEntry::query()
                ->where('scope', BlacklistScope::Global->value)
                ->active()
                ->orderBy('id')
                ->get()
                ->map(fn (BlacklistEntry $entry): array => [$entry->public_id, $entry->type->value, $entry->value, $entry->reason, $entry->expires_at?->toDateTimeString()])
                ->all());

            return self::SUCCESS;
        }

        if (! in_array($action, ['add', 'remove'], true)) {
            $this->components->error('Action must be "add", "remove" or "list".');

            return self::FAILURE;
        }

        $actor = $this->actor();
        $reason = trim((string) $this->option('reason'));

        if ($actor === null) {
            $this->components->error('Actor must be an existing platform super admin.');

            return self::FAILURE;
        }

        if ($reason === '' || mb_strlen($reason) > 255) {
            $this->components->error('A reason (up to 255 characters) is required for audit.');

            return self::FAILURE;
        }

        if (app()->isProduction() && ! $this->option('force') && ! $this->confirm("{$action} a Global blacklist entry as user #{$actor->id}?")) {
            return self::FAILURE;
        }

        return $action === 'add' ? $this->add($blacklist, $actor, $reason) : $this->remove($actor, $reason);
    }

    private function add(Blacklist $blacklist, User $actor, string $reason): int
    {
        $type = BlacklistType::tryFrom((string) $this->option('type'));
        $value = $type !== null ? $blacklist->normalize($type, (string) $this->option('value')) : null;
        $days = $this->option('days');

        if ($type === null || $value === null) {
            $this->components->error('Provide --type=ip|phone and a valid --value.');

            return self::FAILURE;
        }

        if ($days !== null && (! ctype_digit((string) $days) || (int) $days < 1 || (int) $days > 3650)) {
            $this->components->error('--days must be between 1 and 3650.');

            return self::FAILURE;
        }

        $entry = new BlacklistEntry(['reason' => $reason, 'expires_at' => $days !== null ? now()->addDays((int) $days) : null]);
        $entry->scope = BlacklistScope::Global;
        $entry->type = $type;
        $entry->value = $value;
        $entry->created_by_user_id = $actor->id;
        $entry->save();

        Log::info('Global blacklist entry added.', ['entry' => $entry->public_id, 'type' => $type->value, 'actor_user_id' => $actor->id, 'reason' => $reason]);
        $this->components->info("Global blacklist entry {$entry->public_id} added.");

        return self::SUCCESS;
    }

    private function remove(User $actor, string $reason): int
    {
        $entry = BlacklistEntry::query()
            ->where('scope', BlacklistScope::Global->value)
            ->where('public_id', strtolower((string) $this->option('entry')))
            ->active()
            ->first();

        if ($entry === null) {
            $this->components->error('Active Global entry not found.');

            return self::FAILURE;
        }

        $entry->update(['expires_at' => now()]);

        Log::info('Global blacklist entry expired.', ['entry' => $entry->public_id, 'type' => $entry->type->value, 'actor_user_id' => $actor->id, 'reason' => $reason]);
        $this->components->info("Global blacklist entry {$entry->public_id} expired.");

        return self::SUCCESS;
    }

    private function actor(): ?User
    {
        $user = User::query()->where('email', mb_strtolower(trim((string) $this->option('actor'))))->first();

        return $user !== null && PlatformRoleAssignment::query()
            ->where('user_id', $user->id)
            ->where('role', PlatformRole::SuperAdmin->value)
            ->exists() ? $user : null;
    }
}
