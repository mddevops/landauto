<?php

namespace App\Publishing;

use App\Enums\PublicationStatus;
use App\Enums\PublishedVersionStatus;
use App\Enums\PublishFailure;
use App\Models\Publication;
use App\Models\PublishedVersion;
use App\Models\Site;
use App\Models\User;
use App\Publishing\Rendering\PageRenderException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Whole-Site Publish with atomic activation (ADR-006 §5). Only short transactions hold the Site
 * lock: starting the attempt, reserving the version number and the final pointer switch.
 * Validation, snapshots and rendering run outside any transaction. Until the final switch the
 * previous production version stays live; any failure leaves it untouched and never changes the
 * Draft.
 */
final class PublishSite
{
    public function __construct(
        private PublishValidator $validator,
        private PublishedSnapshotBuilder $snapshots,
        private PublishedArtifactBuilder $artifacts,
    ) {}

    public function handle(Site $site, User $actor): PublishOutcome
    {
        $publication = $this->start($site, $actor);

        if ($publication->status === PublicationStatus::Failed) {
            return new PublishOutcome($publication);
        }

        $validation = null;
        $version = null;

        try {
            $validation = $this->validator->validate($site, $actor);

            if (! $validation->passes()) {
                $publication->fail(PublishFailure::ValidationFailed, ['errors' => $validation->errorCodes()]);

                return new PublishOutcome($publication, $validation);
            }

            $this->advance($publication, PublicationStatus::Validating, PublicationStatus::Building);
            $snapshot = $this->snapshots->build($site);
            $version = $this->reserveVersion($site, $publication, $actor, $snapshot);
            $this->artifacts->build($version, $snapshot->assetReferences);
            $this->advance($publication, PublicationStatus::Building, PublicationStatus::Activating);
            $this->activate($site, $publication, $version);
        } catch (Throwable $exception) {
            $this->abort($publication, $version, $exception);

            return new PublishOutcome($publication->refresh(), $validation);
        }

        return new PublishOutcome($publication->refresh(), $validation, $version->refresh());
    }

    /**
     * Creates the attempt under the Site lock. A second attempt while one is still running is
     * recorded as a failed conflict; an attempt stuck longer than the stale limit is abandoned.
     */
    private function start(Site $site, User $actor): Publication
    {
        return DB::transaction(function () use ($site, $actor): Publication {
            Site::query()->whereKey($site->id)->lockForUpdate()->firstOrFail();
            $staleBefore = now()->subMinutes((int) config('publishing.stale_after_minutes'));
            $running = false;

            $inProgress = Publication::query()
                ->where('site_id', $site->id)
                ->whereIn('status', array_map(fn (PublicationStatus $status): string => $status->value, array_filter(PublicationStatus::cases(), fn (PublicationStatus $status): bool => $status->isInProgress())))
                ->lockForUpdate()
                ->get();

            foreach ($inProgress as $other) {
                if ($other->started_at->greaterThan($staleBefore)) {
                    $running = true;

                    continue;
                }

                $this->failVersion($other->version);
                $other->fail(PublishFailure::Internal, ['abandoned' => true]);
            }

            $publication = Publication::query()->create([
                'site_id' => $site->id,
                'actor_user_id' => $actor->id,
                'started_at' => now(),
            ]);

            if ($running) {
                $publication->fail(PublishFailure::Conflict);
            }

            return $publication;
        });
    }

    private function reserveVersion(Site $site, Publication $publication, User $actor, PublishedSnapshot $snapshot): PublishedVersion
    {
        return DB::transaction(function () use ($site, $publication, $actor, $snapshot): PublishedVersion {
            Site::query()->whereKey($site->id)->lockForUpdate()->firstOrFail();
            $this->assertStatus($publication, PublicationStatus::Building);

            $version = PublishedVersion::query()->create([
                'site_id' => $site->id,
                'version_number' => (int) PublishedVersion::query()->where('site_id', $site->id)->max('version_number') + 1,
                'public_manifest_json' => $snapshot->publicManifest,
                'draft_snapshot_json' => $snapshot->draftSnapshot,
                'manifest_hash' => $snapshot->manifestHash,
                'created_by' => $actor->id,
            ]);

            $publication->update(['published_version_id' => $version->id]);

            return $version;
        });
    }

    private function activate(Site $site, Publication $publication, PublishedVersion $version): void
    {
        DB::transaction(function () use ($site, $publication, $version): void {
            $site = Site::query()->whereKey($site->id)->lockForUpdate()->firstOrFail();
            $this->assertStatus($publication, PublicationStatus::Activating);
            $version->refresh();

            if ($version->status !== PublishedVersionStatus::Building) {
                throw new PublishAborted(PublishFailure::Conflict);
            }

            $this->verifyArtifacts($version);

            $version->update(['status' => PublishedVersionStatus::Ready, 'ready_at' => now()]);
            $site->forceFill(['active_published_version_id' => $version->id])->save();
            $publication->succeed($version);
        });
    }

    /**
     * Every manifest Page must have exactly one non-empty artifact whose hash matches its HTML.
     */
    private function verifyArtifacts(PublishedVersion $version): void
    {
        $expected = array_column($version->public_manifest_json['pages'], 'public_id');
        $artifacts = $version->pages()->get(['page_public_id', 'rendered_html', 'content_hash']);

        $valid = $artifacts->count() === count($expected)
            && $artifacts->pluck('page_public_id')->sort()->values()->all() === collect($expected)->sort()->values()->all()
            && $artifacts->every(fn ($page): bool => trim($page->rendered_html) !== '' && hash('sha256', $page->rendered_html) === $page->content_hash);

        if (! $valid) {
            throw new PublishAborted(PublishFailure::ArtifactInvalid);
        }
    }

    /**
     * Moves the attempt forward only if no other request finished it meanwhile (e.g. abandoned it).
     */
    private function advance(Publication $publication, PublicationStatus $from, PublicationStatus $to): void
    {
        DB::transaction(function () use ($publication, $from, $to): void {
            $this->assertStatus($publication, $from);
            $publication->moveTo($to);
        });
    }

    private function assertStatus(Publication $publication, PublicationStatus $expected): void
    {
        Publication::query()->whereKey($publication->id)->lockForUpdate()->first();
        $publication->refresh();

        if ($publication->status !== $expected) {
            throw new PublishAborted(PublishFailure::Conflict);
        }
    }

    private function abort(Publication $publication, ?PublishedVersion $version, Throwable $exception): void
    {
        $failure = match (true) {
            $exception instanceof PublishAborted => $exception->failure,
            $exception instanceof PageRenderException => PublishFailure::RenderFailed,
            default => PublishFailure::Internal,
        };

        DB::transaction(function () use ($publication, $version, $failure): void {
            Publication::query()->whereKey($publication->id)->lockForUpdate()->first();
            $publication->refresh();
            $this->failVersion($version ?? $publication->version);

            if ($publication->status->isInProgress()) {
                $publication->fail($failure);
            }
        });

        // Safe diagnostics only: never the manifest, HTML, renderer output or SQL bindings.
        Log::warning('Site publish failed.', [
            'publication' => $publication->public_id,
            'failure' => $failure->value,
            'exception' => $exception::class,
        ]);
    }

    private function failVersion(?PublishedVersion $version): void
    {
        $version?->refresh();

        if ($version !== null && $version->status === PublishedVersionStatus::Building) {
            $version->update(['status' => PublishedVersionStatus::Failed]);
        }
    }
}
