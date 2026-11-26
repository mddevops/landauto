export const AUTO_DELAY_SECONDS = 8;

export const CONDITION_TYPES = [
    'pages',
    'devices',
    'visit',
    'goals',
    'geo',
    'channel',
    'source',
    'campaign',
] as const;

export type ConditionType = (typeof CONDITION_TYPES)[number];

export const OPERATORS: Record<ConditionType, readonly string[]> = {
    pages: ['contains', 'not_contains', 'equals', 'not_equals', 'regex'],
    devices: ['equals', 'not_equals'],
    visit: ['not_visited', 'visited', 'unique'],
    goals: ['equals', 'not_equals'],
    geo: ['equals', 'not_equals'],
    channel: ['contains', 'not_contains', 'equals', 'not_equals', 'regex'],
    source: ['contains', 'not_contains', 'equals', 'not_equals', 'regex'],
    campaign: ['contains', 'not_contains', 'equals', 'not_equals', 'regex'],
};

export const TYPE_LABELS: Record<ConditionType, string> = {
    pages: 'Страницы',
    devices: 'Устройства',
    visit: 'Посещение сайта',
    goals: 'Цели',
    geo: 'Геотаргетинг',
    channel: 'Канал',
    source: 'Источник',
    campaign: 'Кампания',
};

export const OPERATOR_LABELS: Record<string, string> = {
    contains: 'Содержит',
    not_contains: 'Не содержит',
    equals: 'Равно',
    not_equals: 'Не равно',
    regex: 'Регулярное выражение',
    not_visited: 'Не был на сайте',
    visited: 'Был на сайте',
    unique: 'Уникальный пользователь',
};

export type ConditionRule = {
    type: ConditionType;
    operator: string;
    value: string | number | null;
};

export type ConditionGroup = {
    join: 'and' | 'or';
    rules: ConditionRule[];
};

export type PopupLaunch = {
    auto_show: 'off' | 'auto' | 'once_session' | 'periodic' | 'custom';
    delay_seconds: number;
    period_seconds: number;
    shows: { delay_seconds: number }[];
    confirmation: 'replace' | 'notice';
    conditions: {
        join: 'and' | 'or';
        groups: ConditionGroup[];
    };
};

export const defaultLaunch = (): PopupLaunch => ({
    auto_show: 'off',
    delay_seconds: AUTO_DELAY_SECONDS,
    period_seconds: 60,
    shows: [],
    confirmation: 'replace',
    conditions: { join: 'and', groups: [] },
});

export const defaultRule = (type: ConditionType = 'pages'): ConditionRule => {
    const operator = OPERATORS[type][0];

    if (type === 'visit' && operator === 'not_visited') {
        return { type, operator, value: 30 };
    }

    if (type === 'devices') {
        return { type, operator, value: 'mobile' };
    }

    if (type === 'visit' && operator === 'unique') {
        return { type, operator, value: null };
    }

    return { type, operator, value: '' };
};

const visitorKey = (siteId: string) => `lf.visitor.${siteId}`;
const firstVisitKey = (siteId: string) => `lf.first-visit.${siteId}`;
const sessionKey = (popupId: string) => `lf.popup.session.${popupId}`;
const lastShownKey = (popupId: string) => `lf.popup.last.${popupId}`;

function rememberVisitor(siteId: string): {
    unique: boolean;
    firstVisit: number;
} {
    if (typeof window === 'undefined') {
        return { unique: true, firstVisit: Date.now() };
    }

    const existing = window.localStorage.getItem(visitorKey(siteId));
    const unique = existing === null;

    if (unique) {
        window.localStorage.setItem(visitorKey(siteId), '1');
    }

    const stored = window.localStorage.getItem(firstVisitKey(siteId));
    const firstVisit = stored ? Number(stored) : Date.now();

    if (!stored) {
        window.localStorage.setItem(firstVisitKey(siteId), String(firstVisit));
    }

    return { unique, firstVisit };
}

function utm(name: string): string {
    if (typeof window === 'undefined') {
        return '';
    }

    return new URLSearchParams(window.location.search).get(name) ?? '';
}

function compareText(
    operator: string,
    actual: string,
    expected: string,
): boolean {
    const haystack = actual.toLowerCase();
    const needle = expected.toLowerCase();

    switch (operator) {
        case 'contains':
            return haystack.includes(needle);
        case 'not_contains':
            return !haystack.includes(needle);
        case 'equals':
            return haystack === needle;
        case 'not_equals':
            return haystack !== needle;
        case 'regex': {
            try {
                return new RegExp(expected, 'u').test(actual);
            } catch {
                return false;
            }
        }
        default:
            return false;
    }
}

function ruleMatches(
    rule: ConditionRule,
    siteId: string,
    visitor: { unique: boolean; firstVisit: number },
): boolean {
    const path =
        typeof window === 'undefined'
            ? '/'
            : `${window.location.pathname}${window.location.search}`;
    const mobile =
        typeof window !== 'undefined' &&
        window.matchMedia('(max-width: 767px)').matches;

    switch (rule.type) {
        case 'pages':
            return (
                typeof rule.value === 'string' &&
                compareText(rule.operator, path, rule.value)
            );
        case 'devices': {
            const actual = mobile ? 'mobile' : 'desktop';
            const expected = rule.value === 'desktop' ? 'desktop' : 'mobile';

            return rule.operator === 'equals'
                ? actual === expected
                : actual !== expected;
        }
        case 'visit': {
            if (rule.operator === 'unique') {
                return visitor.unique;
            }

            const days = typeof rule.value === 'number' ? rule.value : 0;
            const elapsed = Date.now() - visitor.firstVisit;
            const windowMs = days * 24 * 60 * 60 * 1000;
            const wasHere = elapsed <= windowMs && !visitor.unique;

            return rule.operator === 'visited' ? wasHere : !wasHere;
        }
        case 'goals':
        case 'geo':
            // Landflow has no geo/goals visitor data: the rule is false.
            return false;
        case 'channel':
            return (
                typeof rule.value === 'string' &&
                compareText(rule.operator, utm('utm_medium'), rule.value)
            );
        case 'source':
            return (
                typeof rule.value === 'string' &&
                compareText(rule.operator, utm('utm_source'), rule.value)
            );
        case 'campaign':
            return (
                typeof rule.value === 'string' &&
                compareText(rule.operator, utm('utm_campaign'), rule.value)
            );
        default:
            return false;
    }
}

function groupMatches(
    group: ConditionGroup,
    siteId: string,
    visitor: { unique: boolean; firstVisit: number },
): boolean {
    const results = group.rules.map((rule) =>
        ruleMatches(rule, siteId, visitor),
    );

    return group.join === 'or' ? results.some(Boolean) : results.every(Boolean);
}

export function conditionsMatch(launch: PopupLaunch, siteId: string): boolean {
    const groups = launch.conditions.groups;

    if (groups.length === 0) {
        return true;
    }

    const visitor = rememberVisitor(siteId);
    const results = groups.map((group) => groupMatches(group, siteId, visitor));

    return launch.conditions.join === 'or'
        ? results.some(Boolean)
        : results.every(Boolean);
}

export function autoShowDelays(launch: PopupLaunch): number[] {
    switch (launch.auto_show) {
        case 'off':
            return [];
        case 'auto':
            return [AUTO_DELAY_SECONDS * 1000];
        case 'once_session':
            return [launch.delay_seconds * 1000];
        case 'periodic':
            return [launch.delay_seconds * 1000];
        case 'custom':
            return launch.shows.map((show) => show.delay_seconds * 1000);
        default:
            return [];
    }
}

export function mayAutoShowNow(launch: PopupLaunch, popupId: string): boolean {
    if (typeof window === 'undefined') {
        return false;
    }

    if (launch.auto_show === 'once_session') {
        return window.sessionStorage.getItem(sessionKey(popupId)) === null;
    }

    if (launch.auto_show === 'periodic') {
        const last = Number(
            window.localStorage.getItem(lastShownKey(popupId)) ?? '0',
        );

        return Date.now() - last >= launch.period_seconds * 1000;
    }

    return true;
}

export function markAutoShown(launch: PopupLaunch, popupId: string): void {
    if (typeof window === 'undefined') {
        return;
    }

    if (launch.auto_show === 'once_session') {
        window.sessionStorage.setItem(sessionKey(popupId), '1');
    }

    window.localStorage.setItem(lastShownKey(popupId), String(Date.now()));
}

export function firstMatchingAutoPopup<
    T extends { public_id: string; name: string; launch?: PopupLaunch },
>(popups: T[], siteId: string): T | null {
    const ranked = [...popups]
        .filter((popup) => {
            const launch = popup.launch ?? defaultLaunch();

            return (
                launch.auto_show !== 'off' &&
                conditionsMatch(launch, siteId) &&
                mayAutoShowNow(launch, popup.public_id)
            );
        })
        .sort((a, b) => {
            const names = a.name.localeCompare(b.name, 'ru');

            return names !== 0 ? names : a.public_id.localeCompare(b.public_id);
        });

    return ranked[0] ?? null;
}
