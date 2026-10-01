<?php

namespace App\Http\Controllers\Auth;

use App\Actions\Auth\AuthenticateYandexUser;
use App\Exceptions\YandexEmailConflictException;
use App\Exceptions\YandexOAuthException;
use App\Http\Controllers\Controller;
use App\Services\Auth\YandexOAuthClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class YandexOAuthController extends Controller
{
    private const SESSION_KEY = 'oauth.yandex';

    public function redirect(Request $request, YandexOAuthClient $client): RedirectResponse
    {
        $state = Str::random(64);
        $codeVerifier = Str::random(96);

        $request->session()->put(self::SESSION_KEY, [
            'state' => $state,
            'code_verifier' => $codeVerifier,
            'issued_at' => now()->timestamp,
        ]);

        try {
            return redirect()->away($client->authorizationUrl(
                $state,
                rtrim(strtr(base64_encode(hash('sha256', $codeVerifier, true)), '+/', '-_'), '='),
            ));
        } catch (YandexOAuthException) {
            $request->session()->forget(self::SESSION_KEY);

            return to_route('login')->withErrors([
                'yandex' => __('Yandex sign-in is temporarily unavailable.'),
            ]);
        }
    }

    public function callback(
        Request $request,
        YandexOAuthClient $client,
        AuthenticateYandexUser $authenticateYandexUser,
    ): RedirectResponse {
        $oauthSession = $request->session()->pull(self::SESSION_KEY);

        if (! $this->hasValidState($request, $oauthSession)) {
            return to_route('login')->withErrors([
                'yandex' => __('The Yandex sign-in request is invalid or expired. Please try again.'),
            ]);
        }

        $code = $request->query('code');
        $codeVerifier = $oauthSession['code_verifier'];

        if (! is_string($code) || $code === '' || $request->query('error') !== null) {
            return to_route('login')->withErrors([
                'yandex' => __('Yandex sign-in was not completed. Please try again.'),
            ]);
        }

        try {
            $profile = $client->fetchProfile($code, $codeVerifier);
            $user = $authenticateYandexUser->authenticate($profile);
        } catch (YandexEmailConflictException) {
            return to_route('login')->withErrors([
                'yandex' => __('An account with this email already exists. Sign in with your existing method, then connect Yandex in account settings.'),
            ]);
        } catch (YandexOAuthException) {
            return to_route('login')->withErrors([
                'yandex' => __('Yandex did not provide the required account data. Allow email access and try again, or register with email.'),
            ]);
        }

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->intended(route('dashboard', absolute: false));
    }

    private function hasValidState(Request $request, mixed $oauthSession): bool
    {
        if (! is_array($oauthSession)) {
            return false;
        }

        $storedState = $oauthSession['state'] ?? null;
        $codeVerifier = $oauthSession['code_verifier'] ?? null;
        $issuedAt = $oauthSession['issued_at'] ?? null;

        if (! is_string($storedState)
            || ! is_string($codeVerifier)
            || ! is_int($issuedAt)
            || now()->getTimestamp() - $issuedAt > 600) {
            return false;
        }

        $state = $request->query('state');

        return is_string($state) && hash_equals($storedState, $state);
    }
}
