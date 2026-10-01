<?php

namespace App\Actions\Auth;

use App\Actions\Accounts\CreateNewAccount;
use App\Data\YandexUserProfile;
use App\Enums\AuthProvider;
use App\Exceptions\YandexEmailConflictException;
use App\Models\User;
use App\Models\UserAuthIdentity;
use Illuminate\Support\Facades\DB;

class AuthenticateYandexUser
{
    public function __construct(private readonly CreateNewAccount $createNewAccount) {}

    public function authenticate(YandexUserProfile $profile): User
    {
        return DB::transaction(function () use ($profile): User {
            $identity = UserAuthIdentity::query()
                ->with('user')
                ->where('provider', AuthProvider::Yandex)
                ->where('provider_user_id', $profile->providerUserId)
                ->lockForUpdate()
                ->first();

            if ($identity !== null) {
                if ($identity->provider_email !== $profile->email) {
                    $identity->update(['provider_email' => $profile->email]);
                }

                return $identity->user;
            }

            if (User::query()->where('email', $profile->email)->exists()) {
                throw new YandexEmailConflictException;
            }

            $user = $this->createNewAccount->create([
                'name' => $profile->name,
                'email' => $profile->email,
                'email_verified_at' => now(),
                'password' => null,
            ]);

            $identity = new UserAuthIdentity([
                'provider' => AuthProvider::Yandex,
                'provider_user_id' => $profile->providerUserId,
                'provider_email' => $profile->email,
            ]);
            $identity->user()->associate($user);
            $identity->save();

            return $user;
        });
    }
}
