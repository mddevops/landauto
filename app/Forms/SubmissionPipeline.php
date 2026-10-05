<?php

namespace App\Forms;

use App\Enums\FormFieldType;
use App\Enums\SiteStatus;
use App\Enums\SubmissionMode;
use App\Forms\Captcha\CaptchaVerdict;
use App\Forms\Captcha\CaptchaVerifier;
use App\Models\Form;
use App\Models\FormField;
use App\Models\PublishedVersion;
use App\Models\Submission;
use App\Support\PhoneNormalizer;
use Closure;
use Illuminate\Support\Str;

/**
 * Canonical public submission pipeline (FORMS_AND_INTEGRATIONS.md §9): resolve the Form,
 * validate, normalize, apply anti-spam, resolve trusted context, then persist the
 * Submission before anything else may happen with it.
 */
class SubmissionPipeline
{
    public const CAPTCHA_KEY = 'captcha_token';

    public const CAPTCHA_MESSAGE = 'Подтвердите, что вы не робот.';

    /** Top-level payload keys a visitor may send; anything else is rejected. */
    public const PAYLOAD_KEYS = ['fields', 'context', 'tracking', SubmissionGuard::HONEYPOT_KEY, self::CAPTCHA_KEY];

    public function __construct(
        private SubmissionFieldValidator $validator,
        private PhoneNormalizer $phones,
        private Blacklist $blacklist,
        private SubmissionGuard $guard,
        private CaptchaVerifier $captcha,
        private SubmissionContextResolver $context,
        private PublishedSubmissionContext $publishedContext,
    ) {}

    /**
     * CAPTCHA applies when the Site requires it and the platform verifier is configured.
     * A missing token is rejected; a provider outage is accepted (fail open).
     */
    private function passesCaptcha(SiteSecurityPolicy $policy, mixed $token, ?string $ip): bool
    {
        if (! $policy->captchaRequired || ! $this->captcha->isConfigured()) {
            return true;
        }

        if (! is_string($token) || $token === '' || strlen($token) > 4096) {
            return false;
        }

        return $this->captcha->verify($token, $ip) !== CaptchaVerdict::Failed;
    }

    /**
     * Draft Form submission from the authenticated preview. It passes the same protection as
     * visitors, but duplicates and rate-limit counters are kept per mode, so testing can never
     * block or shadow real visitor leads. Public leads never use the Draft Form (ADR-006 §7).
     *
     * @param  array<array-key, mixed>  $payload
     */
    public function handlePreview(string $formPublicId, array $payload, ?string $ip, ?string $userAgent): SubmissionResult
    {
        $form = Form::query()->with(['fields', 'site'])->where('public_id', $formPublicId)->first();

        if ($form === null || ! $form->status || $form->site->status !== SiteStatus::Active) {
            return SubmissionResult::unavailable();
        }

        return $this->process(
            $form,
            $form->success_message,
            $payload,
            $ip,
            $userAgent,
            SubmissionMode::Preview,
            fn (mixed $context, mixed $tracking): ?array => $this->context->resolve($form, $context, $tracking),
        );
    }

    /**
     * Visitor submission from a published page. Fields, labels, success text and trusted context
     * come from that Published Version's manifest; the Draft Form row only anchors the Submission.
     * The version must belong to an active, published Site and have reached `ready`; an older
     * ready version keeps accepting its own already-loaded pages.
     *
     * @param  array<array-key, mixed>  $payload
     */
    public function handlePublished(PublishedVersion $version, string $formPublicId, array $payload, ?string $ip, ?string $userAgent): SubmissionResult
    {
        $site = $version->site;
        $definition = null;

        foreach ($version->public_manifest_json['forms'] as $candidate) {
            if ($candidate['public_id'] === $formPublicId) {
                $definition = $candidate;
            }
        }

        $form = $site->forms()->where('public_id', $formPublicId)->first();

        if ($definition === null || $form === null || ! $version->isReady()
            || $site->status !== SiteStatus::Active || $site->active_published_version_id === null) {
            return SubmissionResult::unavailable();
        }

        $form->setRelation('site', $site);
        $form->setRelation('fields', $form->fields()->getRelated()->newCollection(array_map(
            fn (array $field): FormField => self::publishedField($field),
            $definition['fields'],
        )));

        return $this->process(
            $form,
            $definition['success_message'],
            $payload,
            $ip,
            $userAgent,
            SubmissionMode::Public,
            fn (mixed $context, mixed $tracking): ?array => $this->publishedContext->resolve($version, $formPublicId, $context, $tracking),
        );
    }

    /**
     * An unsaved field rebuilt from the published Form definition, used only for validation and
     * the Submission field snapshot.
     *
     * @param  array<string, mixed>  $field
     */
    private static function publishedField(array $field): FormField
    {
        $model = new FormField;
        $model->forceFill([
            'key' => $field['key'],
            'type' => $field['type'],
            'label' => $field['label'],
            'required' => $field['required'],
            'options' => $field['options'] === [] ? null : $field['options'],
            'validation' => $field['max_length'] === null ? null : ['max_length' => $field['max_length']],
        ]);

        return $model;
    }

    /**
     * @param  array<array-key, mixed>  $payload
     * @param  Closure(mixed, mixed): ?array{trusted: array<string, array<string, mixed>>, visitor: array<string, string>}  $resolveContext
     */
    private function process(Form $form, string $successMessage, array $payload, ?string $ip, ?string $userAgent, SubmissionMode $mode, Closure $resolveContext): SubmissionResult
    {
        $fields = $payload['fields'] ?? null;

        if (array_diff(array_keys($payload), self::PAYLOAD_KEYS) !== [] || ! is_array($fields)) {
            return SubmissionResult::invalid([]);
        }

        if ($this->guard->isHoneypotFilled($payload[SubmissionGuard::HONEYPOT_KEY] ?? null)) {
            return SubmissionResult::rejected(SubmissionGuard::REJECTION_MESSAGE);
        }

        $validated = $this->validator->validate($form, $fields);

        if ($validated['errors'] !== []) {
            return SubmissionResult::invalid($validated['errors']);
        }

        $values = $validated['values'];
        $phone = $this->firstValue($form, $values, FormFieldType::Phone);
        $normalizedPhone = $this->phones->normalize($phone);
        $policy = SiteSecurityPolicy::forSite($form->site);

        if ($this->blacklist->blocks($form->site, $ip, $normalizedPhone)) {
            return SubmissionResult::rejected(SubmissionGuard::REJECTION_MESSAGE);
        }

        if ($this->guard->blocks($form, $policy, $ip, $normalizedPhone, $mode)) {
            return SubmissionResult::throttled(SubmissionGuard::REJECTION_MESSAGE);
        }

        if (! $this->passesCaptcha($policy, $payload[self::CAPTCHA_KEY] ?? null, $ip)) {
            return SubmissionResult::rejected(self::CAPTCHA_MESSAGE);
        }

        $context = $resolveContext($payload['context'] ?? null, $payload['tracking'] ?? null);

        if ($context === null) {
            return SubmissionResult::rejected('Данные страницы устарели. Обновите страницу и отправьте заявку снова.');
        }

        $email = $this->firstValue($form, $values, FormFieldType::Email);
        $submission = new Submission([]);
        $submission->forceFill([
            'site_id' => $form->site_id,
            'form_id' => $form->id,
            'mode' => $mode,
            'payload' => array_values($form->fields->map(fn (FormField $field): array => [
                'key' => $field->key,
                'type' => $field->type->value,
                'label' => $field->label,
                'value' => $values[$field->key] ?? null,
            ])->all()),
            'context' => $context['trusted'] === [] && $context['visitor'] === [] ? null : $context,
            'phone_original' => $phone,
            'phone_normalized' => $normalizedPhone,
            'email_normalized' => $email !== null ? Str::lower($email) : null,
            'ip' => $ip,
            'user_agent' => $userAgent !== null ? mb_substr($userAgent, 0, 255) : null,
            'submitted_at' => now(),
        ])->save();

        $this->guard->record($form, $policy, $ip, $normalizedPhone, $mode);

        return SubmissionResult::accepted($submission, $successMessage);
    }

    /**
     * @param  array<string, string|bool|null>  $values
     */
    private function firstValue(Form $form, array $values, FormFieldType $type): ?string
    {
        foreach ($form->fields as $field) {
            if ($field->type === $type && is_string($values[$field->key] ?? null)) {
                return $values[$field->key];
            }
        }

        return null;
    }
}
