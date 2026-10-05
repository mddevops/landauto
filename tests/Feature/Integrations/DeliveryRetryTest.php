<?php

namespace Tests\Feature\Integrations;

use App\Enums\DeliveryOutcome;
use App\Enums\DeliveryStatus;
use App\Enums\DeliveryTrigger;
use App\Enums\WorkspaceRole;
use App\Integrations\Delivery\DeliveryAdapters;
use App\Integrations\Delivery\DeliveryDispatcher;
use App\Integrations\Delivery\DeliveryFailures;
use App\Jobs\ProcessSubmissionDelivery;
use App\Models\Form;
use App\Models\FormRoute;
use App\Models\Site;
use App\Models\Submission;
use App\Models\SubmissionDelivery;
use App\Models\SubmissionDeliveryAttempt;
use App\Models\User;
use App\Support\WorkspaceContext;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\FakeDeliveryAdapter;
use Tests\TestCase;

class DeliveryRetryTest extends TestCase
{
    use RefreshDatabase;

    private Site $site;

    private FormRoute $route;

    private Submission $submission;

    private FakeDeliveryAdapter $adapter;

    protected function setUp(): void
    {
        parent::setUp();

        $this->site = Site::factory()->create();
        $form = Form::factory()->for($this->site)->withLeadFields()->create();
        $this->route = FormRoute::factory()->for($form)->create();
        $this->submission = Submission::factory()->for($form)->create();

        $this->adapter = new FakeDeliveryAdapter;
        $this->app->instance(DeliveryAdapters::class, new DeliveryAdapters([$this->adapter]));
    }

    /**
     * @return array<string, array{int, DeliveryOutcome, string|null}>
     */
    public static function statuses(): array
    {
        return [
            '200' => [200, DeliveryOutcome::Success, null],
            '204' => [204, DeliveryOutcome::Success, null],
            '301' => [301, DeliveryOutcome::PermanentFailure, 'redirect_not_followed'],
            '400' => [400, DeliveryOutcome::PermanentFailure, 'http_400'],
            '401' => [401, DeliveryOutcome::PermanentFailure, 'http_401'],
            '403' => [403, DeliveryOutcome::PermanentFailure, 'http_403'],
            '404' => [404, DeliveryOutcome::PermanentFailure, 'http_404'],
            '408' => [408, DeliveryOutcome::TransientFailure, 'http_408'],
            '422' => [422, DeliveryOutcome::PermanentFailure, 'http_422'],
            '425' => [425, DeliveryOutcome::TransientFailure, 'http_425'],
            '429' => [429, DeliveryOutcome::TransientFailure, 'http_429'],
            '500' => [500, DeliveryOutcome::TransientFailure, 'http_500'],
            '501' => [501, DeliveryOutcome::PermanentFailure, 'http_501'],
            '502' => [502, DeliveryOutcome::TransientFailure, 'http_502'],
            '503' => [503, DeliveryOutcome::TransientFailure, 'http_503'],
            '504' => [504, DeliveryOutcome::TransientFailure, 'http_504'],
        ];
    }

    #[DataProvider('statuses')]
    public function test_http_statuses_are_classified(int $status, DeliveryOutcome $outcome, ?string $code): void
    {
        $result = DeliveryFailures::forHttpStatus($status);

        $this->assertSame([$outcome, $code, $status], [$result->outcome, $result->errorCode, $result->httpStatus]);
        $this->assertSame(DeliveryOutcome::TransientFailure, DeliveryFailures::timeout()->outcome);
        $this->assertSame(DeliveryOutcome::TransientFailure, DeliveryFailures::network()->outcome);
        $this->assertSame(DeliveryOutcome::TransientFailure, DeliveryFailures::dns()->outcome);
    }

    public function test_transient_failures_follow_the_retry_ladder_then_fail(): void
    {
        $this->freezeSecond();
        $this->adapter->queue(...array_fill(0, 6, DeliveryFailures::forHttpStatus(503)));
        app(DeliveryDispatcher::class)->dispatchFor($this->submission);
        $delivery = SubmissionDelivery::query()->sole();

        foreach ([60, 300, 900, 3600] as $index => $delay) {
            $delivery->refresh();
            $this->assertSame([DeliveryStatus::RetryScheduled, $index + 1], [$delivery->status, $delivery->attempt_count]);
            $this->assertEquals(now()->addSeconds($delay), $delivery->next_retry_at);

            $this->travel($delay - 1)->seconds();
            Artisan::call('integrations:dispatch-due-deliveries');
            $this->assertSame($index + 1, $delivery->fresh()?->attempt_count, 'Not due yet.');

            $this->travel(1)->seconds();
            Artisan::call('integrations:dispatch-due-deliveries');
        }

        $delivery->refresh();
        $this->assertSame([DeliveryStatus::Failed, 5, null, 'http_503'], [$delivery->status, $delivery->attempt_count, $delivery->next_retry_at, $delivery->last_error_code]);

        $this->travel(2)->hours();
        Artisan::call('integrations:dispatch-due-deliveries');
        $this->assertCount(5, $this->adapter->calls);
        $this->assertSame([1, 2, 3, 4, 5], SubmissionDeliveryAttempt::query()->orderBy('id')->pluck('attempt_number')->all());
    }

    public function test_duplicate_jobs_and_dispatches_never_repeat_a_provider_call(): void
    {
        app(DeliveryDispatcher::class)->dispatchFor($this->submission);
        app(DeliveryDispatcher::class)->dispatchFor($this->submission);
        $delivery = SubmissionDelivery::query()->sole();

        ProcessSubmissionDelivery::dispatchSync($delivery->id);
        ProcessSubmissionDelivery::dispatchSync($delivery->id);

        $this->assertCount(1, $this->adapter->calls);
        $this->assertSame([DeliveryStatus::Delivered, 1], [$delivery->fresh()?->status, $delivery->fresh()?->attempt_count]);

        $duplicate = new SubmissionDelivery;
        $duplicate->forceFill(['submission_id' => $this->submission->id, 'form_route_id' => $this->route->id, 'destination_type' => $this->route->destination_type]);
        $this->expectException(UniqueConstraintViolationException::class);
        $duplicate->save();
    }

    public function test_a_delivery_claimed_by_another_worker_is_left_alone(): void
    {
        $delivery = $this->delivery(['status' => DeliveryStatus::Processing, 'attempt_count' => 1, 'last_attempt_at' => now()]);

        ProcessSubmissionDelivery::dispatchSync($delivery->id);
        Artisan::call('integrations:dispatch-due-deliveries');

        $this->assertSame([], $this->adapter->calls);
        $this->assertSame(DeliveryStatus::Processing, $delivery->fresh()?->status);
    }

    public function test_stale_processing_and_lost_pending_deliveries_are_recovered(): void
    {
        $stale = $this->delivery(['status' => DeliveryStatus::Processing, 'attempt_count' => 1, 'last_attempt_at' => now()->subMinutes(20)]);
        $open = new SubmissionDeliveryAttempt;
        $open->forceFill(['submission_delivery_id' => $stale->id, 'attempt_number' => 1, 'started_at' => now()->subMinutes(20)])->save();

        $otherRoute = FormRoute::factory()->for($this->submission->form)->create(['name' => 'Вторая почта']);
        $lost = $this->delivery(['form_route_id' => $otherRoute->id]);
        Artisan::call('integrations:dispatch-due-deliveries');
        $this->assertSame(DeliveryStatus::Pending, $lost->fresh()?->status, 'A fresh pending delivery may still be in the queue.');

        $this->travel(16)->minutes();
        Artisan::call('integrations:dispatch-due-deliveries');

        $this->assertSame([DeliveryStatus::Delivered, 2], [$stale->fresh()?->status, $stale->fresh()?->attempt_count]);
        $this->assertSame([DeliveryOutcome::TransientFailure, 'worker_lost'], [$open->fresh()?->status, $open->fresh()?->error_code]);
        $this->assertSame(DeliveryStatus::Delivered, $lost->fresh()?->status);
    }

    public function test_manual_retry_creates_a_new_attempt_and_requires_permission(): void
    {
        $delivery = $this->delivery(['status' => DeliveryStatus::Failed, 'attempt_count' => 5, 'last_error_code' => 'http_503']);
        $submissionRow = Submission::query()->toBase()->find($this->submission->id);
        $url = route('sites.deliveries.retry', [$this->site, $delivery->public_id]);

        $this->as(WorkspaceRole::Designer)->post($url)->assertForbidden();
        $this->as(WorkspaceRole::ContentEditor)->post($url)->assertForbidden();
        $this->assertSame([], $this->adapter->calls);

        $this->as(WorkspaceRole::Admin)->post($url)->assertRedirect();

        $delivery->refresh();
        $this->assertSame([DeliveryStatus::Delivered, 6, null], [$delivery->status, $delivery->attempt_count, $delivery->last_error_code]);
        $attempt = SubmissionDeliveryAttempt::query()->sole();
        $this->assertSame([6, DeliveryTrigger::Manual, DeliveryOutcome::Success], [$attempt->attempt_number, $attempt->trigger, $attempt->status]);
        $this->assertEquals($submissionRow, Submission::query()->toBase()->find($this->submission->id));

        $this->as(WorkspaceRole::Owner)->post($url)->assertRedirect()->assertInertiaFlash('toast.type', 'error');
        $this->assertCount(1, $this->adapter->calls);
    }

    public function test_manual_retry_of_a_foreign_delivery_is_not_found(): void
    {
        $foreignSite = Site::factory()->create();
        $foreignForm = Form::factory()->for($foreignSite)->create();
        $foreign = new SubmissionDelivery;
        $foreignRoute = FormRoute::factory()->for($foreignForm)->create();
        $foreign->forceFill([
            'submission_id' => Submission::factory()->for($foreignForm)->create()->id,
            'form_route_id' => $foreignRoute->id,
            'destination_type' => $foreignRoute->destination_type,
            'status' => DeliveryStatus::Failed,
        ])->save();

        $this->as(WorkspaceRole::Owner)->post(route('sites.deliveries.retry', [$this->site, $foreign->public_id]))->assertNotFound();
        $this->assertSame(DeliveryStatus::Failed, $foreign->fresh()?->status);
    }

    public function test_due_deliveries_command_is_scheduled_every_minute_without_overlap(): void
    {
        $event = collect(Schedule::events())->first(fn ($event): bool => str_contains((string) $event->command, 'integrations:dispatch-due-deliveries'));

        $this->assertNotNull($event);
        $this->assertSame('* * * * *', $event->expression);
        $this->assertTrue($event->withoutOverlapping);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function delivery(array $attributes = []): SubmissionDelivery
    {
        $delivery = new SubmissionDelivery;
        $delivery->forceFill([
            'submission_id' => $this->submission->id,
            'form_route_id' => $this->route->id,
            'destination_type' => $this->route->destination_type,
            ...$attributes,
        ])->save();

        return $delivery;
    }

    private function as(WorkspaceRole $role): self
    {
        $user = User::factory()->create();
        $this->site->workspace->addMember($user, $role);

        return $this->actingAs($user)->withSession([WorkspaceContext::SESSION_KEY => $this->site->workspace->public_id]);
    }
}
