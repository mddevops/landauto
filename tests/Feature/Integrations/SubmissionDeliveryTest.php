<?php

namespace Tests\Feature\Integrations;

use App\Enums\DeliveryDestinationType;
use App\Enums\DeliveryOutcome;
use App\Enums\DeliveryStatus;
use App\Enums\IntegrationStatus;
use App\Enums\SubmissionMode;
use App\Enums\WorkspaceRole;
use App\Integrations\Delivery\DeliveryAdapters;
use App\Integrations\Delivery\DeliveryDispatcher;
use App\Integrations\Delivery\DeliveryResult;
use App\Jobs\ProcessSubmissionDelivery;
use App\Models\Form;
use App\Models\FormRoute;
use App\Models\IntegrationProfile;
use App\Models\Site;
use App\Models\SiteIntegrationBinding;
use App\Models\Submission;
use App\Models\SubmissionDelivery;
use App\Models\SubmissionDeliveryAttempt;
use App\Models\User;
use App\Support\WorkspaceContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use RuntimeException;
use Tests\Concerns\SubmitsPublishedForms;
use Tests\Support\FakeDeliveryAdapter;
use Tests\TestCase;

class SubmissionDeliveryTest extends TestCase
{
    use RefreshDatabase, SubmitsPublishedForms;

    private Site $site;

    private Form $form;

    private IntegrationProfile $profile;

    private FormRoute $email;

    private FormRoute $webhook;

    private FakeDeliveryAdapter $adapter;

    protected function setUp(): void
    {
        parent::setUp();

        $this->site = Site::factory()->create();
        $this->form = Form::factory()->for($this->site)->withLeadFields()->create();
        $this->profile = IntegrationProfile::factory()->create(['workspace_id' => $this->site->workspace_id]);
        $binding = SiteIntegrationBinding::factory()->for($this->site)->create(['integration_profile_id' => $this->profile->id]);
        $this->email = FormRoute::factory()->for($this->form)->create(['sort_order' => 1]);
        $this->webhook = FormRoute::factory()->for($this->form)->webhook($binding)->create(['sort_order' => 2]);
        FormRoute::factory()->for($this->form)->create(['name' => 'Выключен', 'status' => IntegrationStatus::Disabled]);

        $this->adapter = new FakeDeliveryAdapter;
        $this->app->instance(DeliveryAdapters::class, new DeliveryAdapters([$this->adapter]));
    }

    public function test_public_submission_is_persisted_then_delivered_once_per_active_route(): void
    {
        $this->submitPublished($this->form, ['fields' => $this->fields()])->assertCreated();

        $submission = Submission::query()->sole();
        $deliveries = SubmissionDelivery::query()->orderBy('id')->get();

        $this->assertCount(2, $deliveries);
        $this->assertSame([$this->email->id, $this->webhook->id], $deliveries->pluck('form_route_id')->all());
        $this->assertSame([DeliveryDestinationType::Email, DeliveryDestinationType::Webhook], $deliveries->pluck('destination_type')->all());
        $this->assertTrue($deliveries->every(fn (SubmissionDelivery $d): bool => $d->submission_id === $submission->id
            && $d->status === DeliveryStatus::Delivered && $d->attempt_count === 1 && $d->delivered_at !== null));
        $this->assertSame($deliveries->pluck('public_id')->all(), $this->adapter->calls);

        $attempt = SubmissionDeliveryAttempt::query()->firstOrFail();
        $this->assertSame([1, DeliveryOutcome::Success, 200], [$attempt->attempt_number, $attempt->status, $attempt->http_status]);
        $this->assertNotNull($attempt->finished_at);
    }

    public function test_queued_job_carries_only_the_delivery_id(): void
    {
        Queue::fake();

        $this->submitPublished($this->form, ['fields' => $this->fields()])->assertCreated();

        Queue::assertPushed(ProcessSubmissionDelivery::class, 2);
        Queue::assertPushed(ProcessSubmissionDelivery::class, function (ProcessSubmissionDelivery $job): bool {
            $serialized = serialize($job);

            return SubmissionDelivery::query()->whereKey($job->deliveryId)->exists()
                && ! str_contains($serialized, '79991112233') && ! str_contains($serialized, 'Иван');
        });
        $this->assertSame([DeliveryStatus::Pending], SubmissionDelivery::query()->pluck('status')->unique()->values()->all());
        $this->assertSame([], $this->adapter->calls);
    }

    public function test_preview_submissions_never_create_deliveries(): void
    {
        $owner = User::factory()->create();
        $this->site->workspace->addMember($owner, WorkspaceRole::Owner);

        $this->actingAs($owner)->withSession([WorkspaceContext::SESSION_KEY => $this->site->workspace->public_id])
            ->postJson(route('sites.preview.submissions.store', [$this->site, $this->form->public_id]), ['fields' => $this->fields()])
            ->assertCreated();

        $preview = Submission::query()->sole();
        $this->assertSame(SubmissionMode::Preview, $preview->mode);
        app(DeliveryDispatcher::class)->dispatchFor($preview);

        $this->assertSame(0, SubmissionDelivery::query()->count());
        $this->assertSame([], $this->adapter->calls);
    }

    public function test_outage_keeps_the_lead_and_schedules_a_retry(): void
    {
        $this->freezeSecond();
        $this->adapter->queue(
            DeliveryResult::transient('http_503', 'Сервис временно недоступен. Доставка будет повторена.', 503),
            fn () => throw new RuntimeException('connect to crm.example with token factory-token-0000 failed'),
        );

        $this->submitPublished($this->form, ['fields' => $this->fields()])->assertCreated();

        $this->assertSame(1, Submission::query()->count());
        [$email, $webhook] = SubmissionDelivery::query()->orderBy('id')->get()->all();

        $this->assertSame(DeliveryStatus::RetryScheduled, $email->status);
        $this->assertSame(503, $email->last_http_status);
        $this->assertSame('http_503', $email->last_error_code);
        $this->assertEquals(now()->addSeconds(60), $email->next_retry_at);

        $this->assertSame(DeliveryStatus::RetryScheduled, $webhook->status);
        $this->assertSame('internal_error', $webhook->last_error_code);

        $rows = json_encode([SubmissionDelivery::query()->toBase()->get(), SubmissionDeliveryAttempt::query()->toBase()->get()], JSON_THROW_ON_ERROR);
        $this->assertStringNotContainsString('factory-token-0000', $rows);
        $this->assertStringNotContainsString('crm.example', $rows);
    }

    public function test_permanent_failures_and_disabled_profiles_fail_without_looping(): void
    {
        $this->adapter->queue(DeliveryResult::permanent('http_401', 'Сервис отклонил авторизацию.', 401));
        $this->profile->forceFill(['status' => IntegrationStatus::Disabled])->save();

        $this->submitPublished($this->form, ['fields' => $this->fields()])->assertCreated();

        [$email, $webhook] = SubmissionDelivery::query()->orderBy('id')->get()->all();
        $this->assertSame([DeliveryStatus::Failed, 'http_401', null], [$email->status, $email->last_error_code, $email->next_retry_at]);
        $this->assertSame([DeliveryStatus::Failed, 'profile_disabled'], [$webhook->status, $webhook->last_error_code]);
        $this->assertSame([$email->public_id], $this->adapter->calls);
    }

    public function test_missing_adapter_fails_and_processed_deliveries_are_not_claimed_again(): void
    {
        $this->app->instance(DeliveryAdapters::class, new DeliveryAdapters([]));

        $this->submitPublished($this->form, ['fields' => $this->fields()])->assertCreated();

        $delivery = SubmissionDelivery::query()->orderBy('id')->firstOrFail();
        $this->assertSame([DeliveryStatus::Failed, 'adapter_missing'], [$delivery->status, $delivery->last_error_code]);

        ProcessSubmissionDelivery::dispatchSync($delivery->id);
        $this->assertSame(1, $delivery->fresh()?->attempt_count);
    }

    public function test_route_with_deliveries_is_archived_instead_of_deleted(): void
    {
        $owner = User::factory()->create();
        $this->site->workspace->addMember($owner, WorkspaceRole::Owner);
        $this->submitPublished($this->form, ['fields' => $this->fields()])->assertCreated();

        $this->actingAs($owner)->withSession([WorkspaceContext::SESSION_KEY => $this->site->workspace->public_id])
            ->delete(route('sites.forms.routes.destroy', [$this->site, $this->form, $this->email]))
            ->assertRedirect();

        $this->assertSame(IntegrationStatus::Archived, $this->email->fresh()?->status);
        $this->assertSame(2, SubmissionDelivery::query()->count());
    }

    /**
     * @return array<string, mixed>
     */
    private function fields(): array
    {
        return ['name' => 'Иван', 'phone' => '+7 (999) 111-22-33', 'consent' => true];
    }
}
