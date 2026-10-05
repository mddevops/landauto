import { useId, useRef, useState } from 'react';
import type { FormEvent, ReactNode } from 'react';
import { cn } from '@/lib/utils';
import { CaptchaWidget } from './captcha';
import type { CaptchaConfig } from './captcha';

const captchaErrorKey = 'captcha_token';

export type FormFieldType =
    | 'text'
    | 'phone'
    | 'email'
    | 'textarea'
    | 'select'
    | 'checkbox'
    | 'consent'
    | 'hidden';

export type FormFieldRuntime = {
    key: string;
    type: FormFieldType;
    label: string;
    placeholder: string | null;
    default_value: string | null;
    required: boolean;
    max_length: number | null;
    options: string[];
};

/** Visitor-facing Form payload: public ID and stable field keys only. */
export type FormRuntime = {
    public_id: string;
    submit_label: string;
    success_message: string;
    fields: FormFieldRuntime[];
};

export type FormValues = Record<string, string | boolean>;

/** Anti-spam signals collected by the form; the backend decides what they mean. */
export type FormSubmitMeta = { honeypot: string; captchaToken: string | null };

export type FormInteraction = 'start' | 'validation_error';

export type FormSubmitResult =
    | { ok: true; message: string }
    | { ok: false; message: string | null; errors: Record<string, string> };

const inputClass =
    'w-full min-w-0 rounded-(--lf-radius) border border-neutral-300 bg-white px-3 py-2 text-sm text-neutral-900 outline-none focus-visible:border-(--lf-primary) focus-visible:ring-2 focus-visible:ring-(--lf-primary)/30 aria-invalid:border-red-600';

const autoComplete: Partial<Record<string, string>> = {
    name: 'name',
    phone: 'tel',
    email: 'email',
};

function initialValues(form: FormRuntime): FormValues {
    return Object.fromEntries(
        form.fields.map((field) => [
            field.key,
            field.type === 'checkbox' || field.type === 'consent'
                ? false
                : field.type === 'hidden'
                  ? (field.default_value ?? '')
                  : '',
        ]),
    );
}

/** UX-only checks; the backend re-validates everything. */
function clientErrors(
    form: FormRuntime,
    values: FormValues,
): Record<string, string> {
    const errors: Record<string, string> = {};

    for (const field of form.fields) {
        const value = values[field.key];

        if (!field.required || field.type === 'hidden') {
            continue;
        }

        if (field.type === 'consent' && value !== true) {
            errors[field.key] = 'Подтвердите согласие.';
        } else if (field.type === 'checkbox' && value !== true) {
            errors[field.key] = 'Отметьте этот пункт.';
        } else if (typeof value === 'string' && value.trim() === '') {
            errors[field.key] = 'Заполните это поле.';
        }
    }

    return errors;
}

/**
 * Renders a Site Form. Without `onSubmit` (editor preview) the form is display-only.
 */
export function FormView({
    form,
    onSubmit,
    onEvent,
    extra,
    captcha = null,
}: {
    form: FormRuntime;
    onSubmit?: (
        values: FormValues,
        meta: FormSubmitMeta,
    ) => Promise<FormSubmitResult>;
    /** Payload-free interaction signals for analytics. */
    onEvent?: (event: FormInteraction) => void;
    extra?: ReactNode;
    captcha?: CaptchaConfig | null;
}) {
    const id = useId();
    const started = useRef(false);
    const [values, setValues] = useState<FormValues>(() => initialValues(form));
    const [honeypot, setHoneypot] = useState('');
    const [captchaToken, setCaptchaToken] = useState('');
    const [captchaRound, setCaptchaRound] = useState(0);
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [message, setMessage] = useState<string | null>(null);
    const [status, setStatus] = useState<
        'idle' | 'submitting' | 'success' | 'error'
    >('idle');

    if (status === 'success') {
        return (
            <p
                role="status"
                className="rounded-(--lf-radius) bg-green-50 p-4 text-sm font-medium text-green-800"
            >
                {message ?? form.success_message}
            </p>
        );
    }

    async function submit(event: FormEvent) {
        event.preventDefault();

        if (!onSubmit) {
            return;
        }

        const local = clientErrors(form, values);

        if (captcha && captchaToken === '') {
            local[captchaErrorKey] = 'Подтвердите, что вы не робот.';
        }

        setErrors(local);
        setMessage(null);

        if (Object.keys(local).length > 0) {
            setStatus('error');
            onEvent?.('validation_error');

            return;
        }

        setStatus('submitting');
        const result = await onSubmit(values, {
            honeypot,
            captchaToken: captcha ? captchaToken : null,
        });

        if (result.ok) {
            setMessage(result.message);
            setStatus('success');
        } else {
            setErrors(result.errors);
            setMessage(result.message);
            setStatus('error');

            if (Object.keys(result.errors).length > 0) {
                onEvent?.('validation_error');
            }

            if (captcha) {
                setCaptchaToken('');
                setCaptchaRound((round) => round + 1);
            }
        }
    }

    const set = (key: string, value: string | boolean) =>
        setValues((current) => ({ ...current, [key]: value }));

    return (
        <form
            noValidate
            onSubmit={submit}
            onFocus={() => {
                if (onSubmit && !started.current) {
                    started.current = true;
                    onEvent?.('start');
                }
            }}
            className="flex flex-col gap-4"
        >
            {status === 'error' && message && (
                <p
                    role="alert"
                    className="rounded-(--lf-radius) bg-red-50 p-3 text-sm text-red-800"
                >
                    {message}
                </p>
            )}
            {form.fields.map((field) => {
                const fieldId = `${id}-${field.key}`;
                const error = errors[field.key];
                const errorId = `${fieldId}-error`;
                const common = {
                    id: fieldId,
                    name: field.key,
                    'aria-invalid': error ? true : undefined,
                    'aria-describedby': error ? errorId : undefined,
                    'aria-required': field.required || undefined,
                };
                const value = values[field.key];

                if (field.type === 'hidden') {
                    return (
                        <input
                            key={field.key}
                            type="hidden"
                            name={field.key}
                            value={typeof value === 'string' ? value : ''}
                        />
                    );
                }

                if (field.type === 'checkbox' || field.type === 'consent') {
                    return (
                        <div key={field.key} className="flex flex-col gap-1">
                            <div className="flex items-start gap-2">
                                <input
                                    {...common}
                                    type="checkbox"
                                    checked={value === true}
                                    onChange={(event) =>
                                        set(field.key, event.target.checked)
                                    }
                                    className="mt-0.5 size-4 shrink-0 accent-(--lf-primary)"
                                />
                                <label
                                    htmlFor={fieldId}
                                    className="text-sm whitespace-pre-line text-neutral-700"
                                >
                                    {field.label}
                                </label>
                            </div>
                            <FieldError id={errorId} message={error} />
                        </div>
                    );
                }

                const text = typeof value === 'string' ? value : '';

                return (
                    <div key={field.key} className="flex flex-col gap-1.5">
                        <label
                            htmlFor={fieldId}
                            className="text-sm font-medium text-neutral-800"
                        >
                            {field.label}
                            {field.required && (
                                <span
                                    aria-hidden="true"
                                    className="text-red-600"
                                >
                                    {' *'}
                                </span>
                            )}
                        </label>
                        {field.type === 'textarea' ? (
                            <textarea
                                {...common}
                                rows={3}
                                value={text}
                                maxLength={field.max_length ?? undefined}
                                placeholder={field.placeholder ?? undefined}
                                onChange={(event) =>
                                    set(field.key, event.target.value)
                                }
                                className={inputClass}
                            />
                        ) : field.type === 'select' ? (
                            <select
                                {...common}
                                value={text}
                                onChange={(event) =>
                                    set(field.key, event.target.value)
                                }
                                className={inputClass}
                            >
                                <option value="">
                                    {field.placeholder ?? 'Выберите вариант'}
                                </option>
                                {field.options.map((option) => (
                                    <option key={option} value={option}>
                                        {option}
                                    </option>
                                ))}
                            </select>
                        ) : (
                            <input
                                {...common}
                                type={
                                    field.type === 'phone'
                                        ? 'tel'
                                        : field.type === 'email'
                                          ? 'email'
                                          : 'text'
                                }
                                inputMode={
                                    field.type === 'phone' ? 'tel' : undefined
                                }
                                autoComplete={autoComplete[field.key]}
                                value={text}
                                maxLength={field.max_length ?? undefined}
                                placeholder={field.placeholder ?? undefined}
                                onChange={(event) =>
                                    set(field.key, event.target.value)
                                }
                                className={inputClass}
                            />
                        )}
                        <FieldError id={errorId} message={error} />
                    </div>
                );
            })}
            <div aria-hidden="true" className="sr-only">
                <input
                    type="text"
                    name="lf_website"
                    tabIndex={-1}
                    autoComplete="off"
                    value={honeypot}
                    onChange={(event) => setHoneypot(event.target.value)}
                />
            </div>
            {captcha && onSubmit && (
                <div className="flex flex-col gap-1">
                    <CaptchaWidget
                        key={captchaRound}
                        config={captcha}
                        onToken={setCaptchaToken}
                    />
                    <FieldError
                        id={`${id}-captcha-error`}
                        message={errors[captchaErrorKey]}
                    />
                </div>
            )}
            {extra}
            <button
                type="submit"
                disabled={!onSubmit || status === 'submitting'}
                className={cn(
                    'inline-flex items-center justify-center rounded-(--lf-radius) border-2 border-(--lf-primary) bg-(--lf-primary) px-5 py-2 text-sm font-medium text-(--lf-on-primary) disabled:opacity-60',
                )}
            >
                {status === 'submitting' ? 'Отправка…' : form.submit_label}
            </button>
            {!onSubmit && (
                <p className="text-xs text-neutral-500">
                    Отправка доступна в предпросмотре сайта.
                </p>
            )}
        </form>
    );
}

function FieldError({ id, message }: { id: string; message?: string }) {
    return message ? (
        <p id={id} className="text-sm text-red-700">
            {message}
        </p>
    ) : null;
}
