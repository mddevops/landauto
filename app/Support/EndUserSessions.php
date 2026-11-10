<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Ends authenticated sessions for a User (X-013). Production uses the database
 * session driver; other drivers are a no-op here and must not be chosen for launch.
 */
final class EndUserSessions
{
    /**
     * End every stored session for the User (password reset, account deletion).
     */
    public function all(User $user): void
    {
        $this->delete($user);
        $this->rotateRememberToken($user);
    }

    /**
     * End every stored session except the current browser (password / email change).
     */
    public function others(User $user, ?string $exceptSessionId): void
    {
        $this->delete($user, $exceptSessionId);
        $this->rotateRememberToken($user);
    }

    private function delete(User $user, ?string $exceptSessionId = null): void
    {
        if (config('session.driver') !== 'database') {
            return;
        }

        $query = DB::connection(config('session.connection'))
            ->table(config('session.table', 'sessions'))
            ->where('user_id', $user->getKey());

        if (is_string($exceptSessionId) && $exceptSessionId !== '') {
            $query->where('id', '!=', $exceptSessionId);
        }

        $query->delete();
    }

    private function rotateRememberToken(User $user): void
    {
        $user->setRememberToken(Str::random(60));
        $user->save();
    }
}
