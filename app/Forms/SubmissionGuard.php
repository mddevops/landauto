<?php

namespace App\Forms;

use App\Enums\SubmissionMode;
use App\Models\Form;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Centralized anti-spam for every Landflow Form (FORMS_AND_INTEGRATIONS.md §11): honeypot,
 * per-Site IP and normalized-phone limits, and same Form + phone duplicate detection.
 * Counters live in the cache via RateLimiter; only persisted Submissions consume them.
 * Duplicates and counters are separate per Submission mode, so preview tests never
 * affect real visitor leads.
 */
class SubmissionGuard
{
    public const HONEYPOT_KEY = 'lf_hp';

    public const REJECTION_MESSAGE = 'Не удалось отправить заявку. Попробуйте позже или свяжитесь с нами по телефону.';

    public function isHoneypotFilled(mixed $value): bool
    {
        return $value !== null && $value !== '' && $value !== false;
    }

    /**
     * True when the request must be rejected as spam before persisting.
     */
    public function blocks(Form $form, SiteSecurityPolicy $policy, ?string $ip, ?string $phone, SubmissionMode $mode = SubmissionMode::Public): bool
    {
        if ($phone !== null) {
            if ($policy->duplicateWindowMinutes > 0 && $form->submissions()
                ->where('mode', $mode->value)
                ->where('phone_normalized', $phone)
                ->where('submitted_at', '>=', now()->subMinutes($policy->duplicateWindowMinutes))
                ->exists()) {
                return true;
            }

            if (RateLimiter::tooManyAttempts($this->phoneKey($form, $phone, $mode), $policy->phoneLimit)) {
                return true;
            }
        }

        return $ip !== null && RateLimiter::tooManyAttempts($this->ipKey($form, $ip, $mode), $policy->ipLimit);
    }

    /**
     * Count an accepted Submission against the Site limits.
     */
    public function record(Form $form, SiteSecurityPolicy $policy, ?string $ip, ?string $phone, SubmissionMode $mode = SubmissionMode::Public): void
    {
        if ($phone !== null) {
            RateLimiter::hit($this->phoneKey($form, $phone, $mode), $policy->phoneWindowMinutes * 60);
        }

        if ($ip !== null) {
            RateLimiter::hit($this->ipKey($form, $ip, $mode), $policy->ipWindowMinutes * 60);
        }
    }

    private function ipKey(Form $form, string $ip, SubmissionMode $mode): string
    {
        return "form-submissions:{$this->scope($mode)}ip:{$form->site_id}:".hash('sha256', $ip);
    }

    private function phoneKey(Form $form, string $phone, SubmissionMode $mode): string
    {
        return "form-submissions:{$this->scope($mode)}phone:{$form->site_id}:".hash('sha256', $phone);
    }

    private function scope(SubmissionMode $mode): string
    {
        return $mode === SubmissionMode::Public ? '' : "{$mode->value}:";
    }
}
