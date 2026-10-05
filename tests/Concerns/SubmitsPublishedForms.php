<?php

namespace Tests\Concerns;

use App\Enums\PublishedVersionStatus;
use App\Models\Form;
use App\Models\Popup;
use App\Models\PublishedVersion;
use App\Models\Site;
use App\Publishing\PublishedSnapshotBuilder;
use App\Publishing\Runtime\PublicSiteResolver;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Public leads exist only on published pages (ADR-006 §7). These helpers publish the current
 * Draft of a Form's Site (the Form reachable through an active Popup, as on a real page) and post
 * to the version-bound endpoint on the Site host. Rendering is skipped; only the manifest matters.
 */
trait SubmitsPublishedForms
{
    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, string>  $headers
     * @return TestResponse<Response>
     */
    protected function submitPublished(Form $form, array $payload, ?string $ip = null, array $headers = []): TestResponse
    {
        $version = $this->publishFormSite($form);
        $site = Site::query()->findOrFail($form->site_id);
        $request = $ip === null ? $this : $this->withServerVariables(['REMOTE_ADDR' => $ip]);

        return $this->onPublicHost(fn () => $request->postJson($this->publicUrl($site, "/_landflow/forms/{$version->public_id}/{$form->public_id}"), $payload, $headers));
    }

    /**
     * Runs a public-host request and points URL generation back at the application host, so later
     * `route()` calls in the test do not inherit the Site host.
     *
     * @template T
     *
     * @param  Closure(): T  $request
     * @return T
     */
    protected function onPublicHost(Closure $request): mixed
    {
        try {
            return $request();
        } finally {
            $this->app['url']->setRequest(Request::create((string) config('app.url')));
        }
    }

    protected function publishFormSite(Form $form): PublishedVersion
    {
        $site = Site::query()->findOrFail($form->site_id);

        if ($site->subdomain === null) {
            $site->forceFill(['subdomain' => 'site-'.Str::lower(Str::random(10))])->save();
        }

        if (! $site->popups()->active()->where('form_id', $form->id)->exists()) {
            Popup::factory()->for($site)->create()->form()->associate($form)->save();
        }

        return $this->publishSnapshot($site);
    }

    /**
     * Activates a ready version from the current Draft unless the active one already matches it.
     */
    protected function publishSnapshot(Site $site): PublishedVersion
    {
        $snapshot = app(PublishedSnapshotBuilder::class)->build($site);
        $active = $site->activePublishedVersion()->first();

        if ($active !== null && $active->manifest_hash === $snapshot->manifestHash) {
            return $active;
        }

        $version = PublishedVersion::query()->create([
            'site_id' => $site->id,
            'version_number' => (int) PublishedVersion::query()->where('site_id', $site->id)->max('version_number') + 1,
            'public_manifest_json' => $snapshot->publicManifest,
            'draft_snapshot_json' => $snapshot->draftSnapshot,
            'manifest_hash' => $snapshot->manifestHash,
        ]);
        $version->update(['status' => PublishedVersionStatus::Ready, 'ready_at' => now()]);
        $site->forceFill(['active_published_version_id' => $version->id])->save();

        return $version;
    }

    protected function publicUrl(Site $site, string $path = '/'): string
    {
        return (string) PublicSiteResolver::url($site, $path);
    }
}
