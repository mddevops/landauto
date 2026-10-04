<?php

namespace App\Forms;

use App\Models\Form;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Centralized anti-spam for every Landflow Form (FORMS_AND_INTEGRATIONS.md §11): honeypot,
 * per-Site IP and normalized-phone limits, and same Form + phone duplicate detection.
 * Counters live in the cache via RateLimiter; only persisted Submissions consume them.
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
    public function blocks(Form $form, SiteSecurityPolicy $policy, ?string $ip, ?string $phone): bool
    {
        if ($phone !== null) {
            if ($policy->duplicateWindowMinutes > 0 && $form->submissions()
                ->where('phone_normalized', $phone)
                ->where('submitted_at', '>=', now()->subMinutes($policy->duplicateWindowMinutes))
                ->exists()) {
                return true;
            }

            if (RateLimiter::tooManyAttempts($this->phoneKey($form, $phone), $policy->phoneLimit)) {
                return true;
            }
        }

        return $ip !== null && RateLimiter::tooManyAttempts($this->ipKey($form, $ip), $policy->ipLimit);
    }

    /**
     * Count an accepted Submission against the Site limits.
     */
    public function record(Form $form, SiteSecurityPolicy $policy, ?string $ip, ?string $phone): void
    {
        if ($phone !== null) {
            RateLimiter::hit($this->phoneKey($form, $phone), $policy->phoneWindowMinutes * 60);
        }

        if ($ip !== null) {
            RateLimiter::hit($this->ipKey($form, $ip), $policy->ipWindowMinutes * 60);
        }
    }

    private function ipKey(Form $form, string $ip): string
    {
        return "form-submissions:ip:{$form->site_id}:".hash('sha256', $ip);
    }

    private function phoneKey(Form $form, string $phone): string
    {
        return "form-submissions:phone:{$form->site_id}:".hash('sha256', $phone);
    }
}
