<?php

namespace Tests\Feature\Integrations;

use App\Enums\BlacklistScope;
use App\Enums\BlacklistType;
use App\Enums\DeliveryStatus;
use App\Enums\FormFieldType;
use App\Integrations\Delivery\DeliveryDispatcher;
use App\Mail\SubmissionLeadMail;
use App\Models\BlacklistEntry;
use App\Models\Form;
use App\Models\FormField;
use App\Models\FormRoute;
use App\Models\Site;
use App\Models\Submission;
use App\Models\SubmissionDelivery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mailer\Exception\TransportException;
use Tests\TestCase;

class EmailDeliveryTest extends TestCase
{
    use RefreshDatabase;

    private Site $site;

    private Form $form;

    private FormRoute $route;

    protected function setUp(): void
    {
        parent::setUp();

        $this->site = Site::factory()->create(['name' => 'Дилер Москвич', 'subdomain' => 'dealer']);
        $this->form = Form::factory()->for($this->site)->withLeadFields()->create(['name' => 'Тест-драйв']);
        FormField::factory()->for($this->form)->create(['key' => 'email', 'type' => FormFieldType::Email, 'label' => 'Почта', 'required' => false, 'sort_order' => 9]);
        $this->route = FormRoute::factory()->for($this->form)->create([
            'email_destination' => ['sales@example.com', 'boss@example.com'],
            'settings_json' => ['subject' => 'Заявка: {form.name} — {vehicle.title} ({offer.price})', 'reply_to_field' => 'email'],
        ]);
    }

    public function test_lead_email_is_sent_with_safe_subject_fields_and_context(): void
    {
        Mail::fake();
        $submission = $this->submission();

        app(DeliveryDispatcher::class)->dispatchFor($submission);

        Mail::assertSent(SubmissionLeadMail::class, function (SubmissionLeadMail $mail) use ($submission): bool {
            $this->assertTrue($mail->hasTo('sales@example.com') && $mail->hasTo('boss@example.com'));
            $this->assertTrue($mail->hasReplyTo('Client@Example.RU'));
            $this->assertSame('Заявка: Тест-драйв — Москвич 3 (1 990 000 ₽)', $mail->subjectLine);

            $html = $mail->render();
            $this->assertStringContainsString('Дилер Москвич', $html);
            $this->assertStringContainsString('&lt;b&gt;Иван&lt;/b&gt;', $html);
            $this->assertStringNotContainsString('<b>Иван</b>', $html);
            $this->assertStringContainsString('+7 (999) 111-22-33', $html);
            $this->assertStringContainsString('Да', $html);
            $this->assertStringContainsString('1.5 CVT', $html);
            $this->assertStringContainsString('yandex', $html);
            $this->assertStringContainsString($submission->public_id, $html);
            $this->assertStringNotContainsString('203.0.113.9', $html);
            $this->assertStringNotContainsString('SecretAgent', $html);
            $this->assertStringNotContainsString('198.51.100.7', $html);

            return true;
        });

        $delivery = SubmissionDelivery::query()->sole();
        $this->assertSame(DeliveryStatus::Delivered, $delivery->status);
        $this->assertSame(['recipients' => 2], $delivery->attempts()->sole()->response_summary_json);
    }

    public function test_subject_values_cannot_inject_headers(): void
    {
        Mail::fake();
        $submission = $this->submission(['vehicle' => ['title' => "Москвич\r\nBcc: spy@example.com"]]);

        app(DeliveryDispatcher::class)->dispatchFor($submission);

        Mail::assertSent(SubmissionLeadMail::class, function (SubmissionLeadMail $mail): bool {
            $this->assertStringNotContainsString("\n", $mail->subjectLine);
            $this->assertStringNotContainsString("\r", $mail->subjectLine);
            $this->assertFalse($mail->hasBcc('spy@example.com'));

            return true;
        });
    }

    public function test_invalid_reply_to_is_dropped_and_invalid_recipients_fail_permanently(): void
    {
        Mail::fake();
        app(DeliveryDispatcher::class)->dispatchFor($this->submission([], 'not-an-email'));
        Mail::assertSent(SubmissionLeadMail::class, fn (SubmissionLeadMail $mail): bool => $mail->replyTo === []);

        $this->route->forceFill(['email_destination' => ['broken', "x@example.com\r\nBcc: y@example.com"]])->save();
        app(DeliveryDispatcher::class)->dispatchFor($this->submission());

        $failed = SubmissionDelivery::query()->latest('id')->firstOrFail();
        $this->assertSame([DeliveryStatus::Failed, 'invalid_recipients'], [$failed->status, $failed->last_error_code]);
        Mail::assertSentCount(1);
    }

    public function test_mail_transport_outage_is_retried(): void
    {
        Mail::shouldReceive('to')->once()->andThrow(new TransportException('Connection to smtp.example:587 failed, password=hunter2'));

        app(DeliveryDispatcher::class)->dispatchFor($this->submission());

        $delivery = SubmissionDelivery::query()->sole();
        $this->assertSame([DeliveryStatus::RetryScheduled, 'mail_transport_error'], [$delivery->status, $delivery->last_error_code]);
        $this->assertStringNotContainsString('hunter2', (string) json_encode(SubmissionDelivery::query()->toBase()->get()));
    }

    /**
     * @param  array<string, array<string, mixed>>  $trusted
     */
    private function submission(array $trusted = [], string $email = 'Client@Example.RU'): Submission
    {
        $entry = new BlacklistEntry;
        $entry->scope = BlacklistScope::Site;
        $entry->type = BlacklistType::Ip;
        $entry->value = '198.51.100.7';
        $entry->site_id = $this->site->id;
        $entry->save();

        return Submission::factory()->for($this->form)->create([
            'payload' => [
                ['key' => 'name', 'type' => 'text', 'label' => 'Имя', 'value' => '<b>Иван</b>'],
                ['key' => 'phone', 'type' => 'phone', 'label' => 'Телефон', 'value' => '+7 (999) 111-22-33'],
                ['key' => 'consent', 'type' => 'consent', 'label' => 'Согласие', 'value' => true],
                ['key' => 'email', 'type' => 'email', 'label' => 'Почта', 'value' => $email],
            ],
            'context' => [
                'trusted' => array_replace_recursive([
                    'vehicle' => ['public_id' => '01hzzzzzzzzzzzzzzzzzzzzzzz', 'title' => 'Москвич 3'],
                    'offer' => ['public_id' => '01hyyyyyyyyyyyyyyyyyyyyyyy', 'modification' => '1.5 CVT', 'price_minor' => 199_000_000, 'currency' => 'RUB', 'price_label' => '1 990 000 ₽'],
                ], $trusted),
                'visitor' => ['utm_source' => 'yandex', 'page_url' => 'https://dealer.example/'],
            ],
            'ip' => '203.0.113.9',
            'user_agent' => 'SecretAgent/1.0',
        ]);
    }
}
