<?php

namespace App\Integrations\Delivery;

use App\Enums\DeliveryDestinationType;
use App\Integrations\Mapping\MappingContext;
use App\Integrations\Mapping\MappingSources;
use App\Mail\SubmissionLeadMail;
use App\Models\IntegrationProfile;
use App\Models\SubmissionDelivery;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

/**
 * Email route delivery through Laravel Mail (FORMS_AND_INTEGRATIONS.md §19). The letter carries
 * the Form, Site, submitted fields, vehicle/offer snapshot, page and UTM data, never IPs, user
 * agents, internal numeric IDs, blacklist data or integration credentials.
 */
final class EmailDeliveryAdapter implements DeliveryAdapter
{
    /** Context rows: mapping source → Russian label. */
    private const CONTEXT_ROWS = [
        'vehicle.title' => 'Автомобиль',
        'vehicle.color' => 'Цвет',
        'offer.modification' => 'Модификация',
        'offer.equipment' => 'Комплектация',
        'offer.price_label' => 'Цена',
        'page.title' => 'Страница',
        'page.url' => 'Адрес страницы',
        'popup.name' => 'Попап',
        'referrer' => 'Источник перехода',
        'utm.source' => 'utm_source',
        'utm.medium' => 'utm_medium',
        'utm.campaign' => 'utm_campaign',
        'utm.content' => 'utm_content',
        'utm.term' => 'utm_term',
    ];

    public function supports(DeliveryDestinationType $type): bool
    {
        return $type === DeliveryDestinationType::Email;
    }

    public function deliver(SubmissionDelivery $delivery): DeliveryResult
    {
        $route = $delivery->route;
        $recipients = array_values(array_filter(
            $route->email_destination ?? [],
            fn (string $email): bool => filter_var($email, FILTER_VALIDATE_EMAIL) !== false,
        ));

        if ($recipients === []) {
            return DeliveryResult::permanent('invalid_recipients', 'Не указаны корректные адреса получателей.');
        }

        $submission = $delivery->submission;
        $context = MappingContext::for($submission);
        $settings = $route->settings_json ?? [];

        $subjectValues = [];

        foreach (EmailSubject::PLACEHOLDERS as $source) {
            $value = MappingSources::resolve($source, $context);
            $subjectValues[$source] = $value === null ? null : (string) $value;
        }

        $replyKey = $settings['reply_to_field'] ?? null;
        $replyTo = is_string($replyKey) ? ($context->fields[$replyKey] ?? null) : null;
        $replyTo = is_string($replyTo) && filter_var($replyTo, FILTER_VALIDATE_EMAIL) !== false ? $replyTo : null;

        $mail = new SubmissionLeadMail(
            EmailSubject::render(is_string($settings['subject'] ?? null) ? $settings['subject'] : null, $subjectValues),
            [
                'form' => $context->formName,
                'site' => $context->siteName,
                'site_url' => $context->siteUrl === '' ? null : $context->siteUrl,
                'submission' => $context->submissionPublicId,
                'submitted_at' => $submission->submitted_at->format('d.m.Y H:i'),
                'fields' => array_map(fn (array $field): array => [
                    'label' => $field['label'],
                    'value' => match (true) {
                        $field['value'] === true => 'Да',
                        $field['value'] === false => 'Нет',
                        $field['value'] === null || $field['value'] === '' => '—',
                        default => $field['value'],
                    },
                ], $submission->payload),
                'context' => $this->contextRows($context),
            ],
            $replyTo,
        );

        try {
            Mail::to($recipients)->send($mail);
        } catch (TransportExceptionInterface) {
            return DeliveryResult::transient('mail_transport_error', 'Почтовый сервис временно недоступен. Доставка будет повторена.');
        }

        return DeliveryResult::success(metadata: ['recipients' => count($recipients)]);
    }

    public function testConnection(IntegrationProfile $profile): DeliveryResult
    {
        return DeliveryResult::permanent('not_supported', 'Проверка подключения недоступна для почты.');
    }

    /**
     * @return list<array{label: string, value: string}>
     */
    private function contextRows(MappingContext $context): array
    {
        $rows = [];

        foreach (self::CONTEXT_ROWS as $source => $label) {
            $value = MappingSources::resolve($source, $context);

            if ($value !== null && $value !== '') {
                $rows[] = ['label' => $label, 'value' => (string) $value];
            }
        }

        return $rows;
    }
}
