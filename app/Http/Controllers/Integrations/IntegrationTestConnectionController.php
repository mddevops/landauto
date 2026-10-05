<?php

namespace App\Http\Controllers\Integrations;

use App\Enums\DeliveryDestinationType;
use App\Enums\WorkspacePermission;
use App\Http\Controllers\Controller;
use App\Integrations\Delivery\DeliveryAdapters;
use App\Integrations\IntegrationProfiles;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Inertia\Inertia;

/**
 * «Проверить подключение» (FORMS_AND_INTEGRATIONS.md §34, SECURITY.md §46): a server-side request
 * through the same adapter, outbound policy, timeouts, auth builder and classifier as real
 * delivery, without creating a Submission. Only success, HTTP status and latency are returned.
 */
class IntegrationTestConnectionController extends Controller
{
    public function __construct(private IntegrationProfiles $profiles) {}

    public function __invoke(Request $request, string $profile, DeliveryAdapters $adapters): RedirectResponse
    {
        Gate::authorize(WorkspacePermission::ManageIntegrations->value);
        $model = $this->profiles->find($profile);

        $key = 'integration-test:'.$request->user()?->getAuthIdentifier();

        if (RateLimiter::tooManyAttempts($key, config()->integer('integrations.test_connection_per_minute'))) {
            Inertia::flash('toast', ['type' => 'error', 'message' => 'Слишком много проверок. Попробуйте через минуту.']);

            return back();
        }

        RateLimiter::hit($key, 60);

        $adapter = $adapters->for(DeliveryDestinationType::from($model->provider_type->value));
        $result = $adapter?->testConnection($model);

        if ($result === null) {
            Inertia::flash('toast', ['type' => 'error', 'message' => 'Проверка для этого подключения недоступна.']);
        } elseif ($result->succeeded()) {
            Inertia::flash('toast', ['type' => 'success', 'message' => "Подключение работает: HTTP {$result->httpStatus}, {$result->latencyMs} мс."]);
        } else {
            $status = $result->httpStatus === null ? '' : " (HTTP {$result->httpStatus})";
            Inertia::flash('toast', ['type' => 'error', 'message' => "Проверка не прошла: {$result->safeMessage}{$status}"]);
        }

        return back();
    }
}
