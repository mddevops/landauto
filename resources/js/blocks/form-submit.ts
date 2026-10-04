import { store } from '@/actions/App/Http/Controllers/Forms/FormSubmissionController';
import type { FormSubmitResult, FormValues } from './form';

const fallbackMessage = 'Не удалось отправить заявку. Попробуйте ещё раз.';

export type SubmissionContext = {
    page?: string;
    block?: string;
    popup?: string;
    vehicle?: string;
    offer?: string;
    media_set?: string;
};

export type SubmissionPayload = {
    fields: FormValues;
    context: SubmissionContext;
    tracking: Record<string, string>;
    lf_hp: string;
};

const utmKeys = [
    'utm_source',
    'utm_medium',
    'utm_campaign',
    'utm_content',
    'utm_term',
];

/** Visitor-sourced page URL, referrer and UTMs; the backend stores them as untrusted. */
export function currentTracking(): Record<string, string> {
    const tracking: Record<string, string> = {
        page_url: window.location.href,
    };
    const params = new URLSearchParams(window.location.search);

    if (document.referrer) {
        tracking.referrer = document.referrer;
    }

    for (const key of utmKeys) {
        const value = params.get(key);

        if (value) {
            tracking[key] = value;
        }
    }

    return tracking;
}

/**
 * Posts a visitor submission to the public Form endpoint. The payload carries only
 * field values and public-ID hints; the backend resolves everything authoritative.
 */
export async function submitForm(
    formPublicId: string,
    payload: SubmissionPayload,
): Promise<FormSubmitResult> {
    let response: Response;

    try {
        response = await fetch(store.url(formPublicId), {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(payload),
        });
    } catch {
        return { ok: false, message: fallbackMessage, errors: {} };
    }

    const data = (await response.json().catch(() => ({}))) as {
        message?: unknown;
        errors?: unknown;
    };
    const message =
        typeof data.message === 'string' ? data.message : fallbackMessage;

    if (response.ok) {
        return { ok: true, message };
    }

    const errors: Record<string, string> = {};

    if (typeof data.errors === 'object' && data.errors !== null) {
        for (const [key, value] of Object.entries(data.errors)) {
            if (typeof value === 'string') {
                errors[key] = value;
            }
        }
    }

    return { ok: false, message, errors };
}
