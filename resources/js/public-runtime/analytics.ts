/**
 * Semantic analytics events of the published runtime (P6-013). Events carry no payload at all:
 * no field values, contacts, IP or IDs ever leave through analytics (SECURITY.md §87).
 */
export type AnalyticsEvent =
    | 'form.start'
    | 'form.submit'
    | 'form.validation_error'
    | 'form.success'
    | 'popup.open'
    | 'popup.close'
    | 'vehicle.form_submit';

type Listener = (event: AnalyticsEvent) => void;

const listeners = new Set<Listener>();

/** Analytics must never break the site: listener failures are swallowed. */
export function track(event: AnalyticsEvent): void {
    for (const listener of listeners) {
        try {
            listener(event);
        } catch {
            // Ignored on purpose.
        }
    }
}

/** Yandex Metrica goal IDs; they must not contain / \ & # ? = " (reachGoal contract). */
export const metricaGoals: Record<AnalyticsEvent, string> = {
    'form.start': 'form_start',
    'form.submit': 'form_submit',
    'form.validation_error': 'form_validation_error',
    'form.success': 'form_success',
    'popup.open': 'popup_open',
    'popup.close': 'popup_close',
    'vehicle.form_submit': 'vehicle_form_submit',
};

type Ym = (counter: number, method: 'reachGoal', target: string) => void;

let metricaConnected = false;

/**
 * Routes events to `ym(counter, 'reachGoal', goal)`. The published page defines `window.ym`
 * only when Metrica is enabled, so a missing counter or a blocked loader is a silent no-op.
 */
export function connectMetrica(counter: string | null): void {
    if (
        metricaConnected ||
        counter === null ||
        !/^[1-9]\d{3,14}$/.test(counter)
    ) {
        return;
    }

    metricaConnected = true;
    const id = Number(counter);

    listeners.add((event) => {
        const ym = (window as { ym?: unknown }).ym;

        if (typeof ym === 'function') {
            (ym as Ym)(id, 'reachGoal', metricaGoals[event]);
        }
    });
}
