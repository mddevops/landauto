<?php

namespace App\Services\Auth;

use App\Data\YandexUserProfile;
use App\Exceptions\YandexOAuthException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class YandexOAuthClient
{
    public function authorizationUrl(string $state, string $codeChallenge): string
    {
        $clientId = $this->configString('client_id');

        return $this->configString('authorize_url').'?'.http_build_query([
            'response_type' => 'code',
            'client_id' => $clientId,
            'redirect_uri' => $this->configString('redirect_uri'),
            'scope' => 'login:email login:info',
            'state' => $state,
            'code_challenge' => $codeChallenge,
            'code_challenge_method' => 'S256',
        ], '', '&', PHP_QUERY_RFC3986);
    }

    public function fetchProfile(string $code, string $codeVerifier): YandexUserProfile
    {
        try {
            $tokenResponse = Http::asForm()
                ->withBasicAuth($this->configString('client_id'), $this->configString('client_secret'))
                ->timeout(10)
                ->post($this->configString('token_url'), [
                    'grant_type' => 'authorization_code',
                    'code' => $code,
                    'redirect_uri' => $this->configString('redirect_uri'),
                    'code_verifier' => $codeVerifier,
                ])
                ->throw();

            $accessToken = $tokenResponse->json('access_token');

            if (! is_string($accessToken) || $accessToken === '') {
                throw new YandexOAuthException('Yandex did not return an access token.');
            }

            $profile = Http::withHeaders(['Authorization' => 'OAuth '.$accessToken])
                ->acceptJson()
                ->timeout(10)
                ->get($this->configString('profile_url'), ['format' => 'json'])
                ->throw()
                ->json();
        } catch (ConnectionException|RequestException $exception) {
            throw new YandexOAuthException('Yandex OAuth request failed.', previous: $exception);
        }

        if (! is_array($profile)) {
            throw new YandexOAuthException('Yandex returned an invalid profile.');
        }

        return $this->normalizeProfile($profile);
    }

    /**
     * @param  array<string, mixed>  $profile
     */
    private function normalizeProfile(array $profile): YandexUserProfile
    {
        $providerUserId = $profile['id'] ?? null;
        $providerClientId = $profile['client_id'] ?? null;
        $email = $profile['default_email'] ?? null;

        if (! is_string($providerUserId) || $providerUserId === '') {
            throw new YandexOAuthException('Yandex profile has no stable user identifier.');
        }

        if (! is_string($providerClientId) || ! hash_equals($this->configString('client_id'), $providerClientId)) {
            throw new YandexOAuthException('Yandex profile belongs to another OAuth client.');
        }

        if (! is_string($email)) {
            throw new YandexOAuthException('Yandex profile has no usable email.');
        }

        $email = Str::lower(trim($email));

        if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new YandexOAuthException('Yandex profile has no usable email.');
        }

        return new YandexUserProfile(
            providerUserId: $providerUserId,
            email: $email,
            name: $this->profileName($profile, $email),
        );
    }

    /**
     * @param  array<string, mixed>  $profile
     */
    private function profileName(array $profile, string $email): string
    {
        foreach (['display_name', 'real_name', 'login'] as $key) {
            $value = $profile[$key] ?? null;

            if (is_string($value) && trim($value) !== '') {
                return Str::limit(trim($value), 255, '');
            }
        }

        return Str::before($email, '@');
    }

    private function configString(string $key): string
    {
        $value = config("services.yandex.$key");

        if (! is_string($value) || $value === '') {
            throw new YandexOAuthException("Yandex OAuth configuration is missing: $key.");
        }

        return $value;
    }
}
