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

export const CANVAS_BLOCKS = [
    'logo',
    'title',
    'subtitle',
    'name',
    'phone',
    'email',
    'comment',
    'call_time',
    'department',
    'button',
    'consent',
    'copyright',
    'image',
    'close',
    'ad_label',
    'promo_timer',
    'backdrop',
] as const;

export const CONFIRM_BLOCKS = [
    'icon',
    'timer',
    'title',
    'subtitle',
    'close',
    'button',
    'copyright',
    'image',
    'logo',
    'background',
] as const;

export type CanvasBlock = (typeof CANVAS_BLOCKS)[number];
export type ConfirmBlock = (typeof CONFIRM_BLOCKS)[number];
export type ShowScenario =
    | 'auto'
    | 'off'
    | 'once_session'
    | 'periodic'
    | 'custom';
export type FormPosition =
    | 'left'
    | 'center'
    | 'bottom-left'
    | 'bottom-center'
    | 'bottom-right';

export type PopupAppearance = {
    height: number;
    height_auto: boolean;
    gradient: boolean;
    color_from: string;
    opacity_from: number;
    color_to: string;
    opacity_to: number;
    angle: number;
    border_color: string;
    border_opacity: number;
    border_width: number;
    pad_left: number;
    pad_right: number;
    pad_top: number;
    pad_bottom: number;
    theme: 'light' | 'dark';
    confirm_width: number;
    confirm_height: number;
    confirm_height_auto: boolean;
    confirm_radius: number;
    confirm_element_radius: number;
    button_label: string;
    button_color: string;
    button_height: number;
    title_size: number;
    title_color: string;
    title_align: 'left' | 'center' | 'right';
    subtitle_size: number;
    illustration_kind: IllustrationKind;
    illustration_width: number;
    illustration_height: number;
    illustration_opacity: number;
    illustration_corner: Corner;
    illustration_x: number;
    illustration_y: number;
    backdrop_shape: 'circle';
    backdrop_width: number;
    backdrop_height: number;
    backdrop_gradient: boolean;
    backdrop_from: string;
    backdrop_from_opacity: number;
    backdrop_to: string;
    backdrop_to_opacity: number;
    backdrop_angle: number;
    backdrop_corner: Corner;
    backdrop_x: number;
    backdrop_y: number;
    call_time_variant: 'bullets' | 'row';
    call_time_size: number;
    call_time_line: number;
    call_time_color: string;
    call_time_bullet_color: string;
    call_time_link_color: string;
    call_time_align: 'left' | 'center' | 'right';
};

export type Corner = 'top-left' | 'top-right' | 'bottom-left' | 'bottom-right';
export type IllustrationKind = 'manager' | 'manager_woman' | 'car';

export const defaultAppearance = (): PopupAppearance => ({
    height: 296,
    height_auto: true,
    gradient: false,
    color_from: '#FFFFFF',
    opacity_from: 100,
    color_to: '#28ADCB',
    opacity_to: 100,
    angle: 240,
    border_color: '#C76EEC',
    border_opacity: 0,
    border_width: 1,
    pad_left: 24,
    pad_right: 24,
    pad_top: 24,
    pad_bottom: 18,
    theme: 'light',
    confirm_width: 300,
    confirm_height: 150,
    confirm_height_auto: true,
    confirm_radius: 16,
    confirm_element_radius: 4,
    button_label: 'Жду звонка',
    button_color: '#2AA3C7',
    button_height: 44,
    title_size: 24,
    title_color: '#1F282C',
    title_align: 'left',
    subtitle_size: 14,
    illustration_kind: 'manager',
    illustration_width: 107,
    illustration_height: 230,
    illustration_opacity: 100,
    illustration_corner: 'bottom-right',
    illustration_x: 28,
    illustration_y: 0,
    backdrop_shape: 'circle',
    backdrop_width: 419,
    backdrop_height: 419,
    backdrop_gradient: true,
    backdrop_from: '#28ADCB',
    backdrop_from_opacity: 100,
    backdrop_to: '#C76EEC',
    backdrop_to_opacity: 100,
    backdrop_angle: 240,
    backdrop_corner: 'bottom-right',
    backdrop_x: -85,
    backdrop_y: -30,
    call_time_variant: 'bullets',
    call_time_size: 12,
    call_time_line: 14,
    call_time_color: '#1F282C',
    call_time_bullet_color: '#C76EEC',
    call_time_link_color: '#28ADCB',
    call_time_align: 'left',
});

export type PopupCanvas = {
    desktop_scenario: ShowScenario;
    mobile_scenario: ShowScenario;
    preset: string;
    desktop_blocks: Record<CanvasBlock, boolean>;
    mobile_blocks: Record<CanvasBlock, boolean>;
    confirmation_kind: 'notice' | 'replace';
    close_scenario: 'auto' | 'manual';
    close_after_seconds: number;
    confirm_blocks: Record<ConfirmBlock, boolean>;
    width: number;
    radius: number;
    element_radius: number;
    position: FormPosition;
    appearance: PopupAppearance;
    appearance_mobile: PopupAppearance;
};

const allOn = <T extends string>(keys: readonly T[]): Record<T, boolean> =>
    Object.fromEntries(keys.map((key) => [key, true])) as Record<T, boolean>;

export const defaultCanvas = (): PopupCanvas => ({
    desktop_scenario: 'auto',
    mobile_scenario: 'off',
    preset: '',
    desktop_blocks: allOn(CANVAS_BLOCKS),
    mobile_blocks: allOn(CANVAS_BLOCKS),
    confirmation_kind: 'notice',
    close_scenario: 'auto',
    close_after_seconds: 5,
    confirm_blocks: {
        ...allOn(CONFIRM_BLOCKS),
        logo: false,
        background: false,
    },
    width: 300,
    radius: 16,
    element_radius: 10,
    position: 'bottom-right',
    appearance: defaultAppearance(),
    appearance_mobile: defaultAppearance(),
});

/** Starting point for a new popup, matching the widget reference screens. */
export const screenshotCanvas = (): PopupCanvas => {
    const appearance = {
        ...defaultAppearance(),
        button_color: '#28ADCB',
        button_height: 34,
    };

    return {
        ...defaultCanvas(),
        width: 478,
        radius: 0,
        element_radius: 0,
        position: 'center',
        appearance,
        appearance_mobile: { ...appearance },
        desktop_blocks: {
            ...allOn(CANVAS_BLOCKS),
            logo: false,
            name: false,
            email: false,
            comment: false,
            department: false,
            ad_label: false,
            promo_timer: false,
        },
        mobile_blocks: {
            ...allOn(CANVAS_BLOCKS),
            logo: false,
            subtitle: false,
            name: false,
            email: false,
            comment: false,
            department: false,
            image: false,
            backdrop: false,
            ad_label: false,
            promo_timer: false,
        },
        confirm_blocks: {
            ...allOn(CONFIRM_BLOCKS),
            timer: false,
            button: false,
            copyright: false,
            image: false,
        },
    };
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
        name: string;
        enabled: boolean;
    };
    canvas: PopupCanvas;
};

export const defaultLaunch = (): PopupLaunch => ({
    auto_show: 'off',
    delay_seconds: AUTO_DELAY_SECONDS,
    period_seconds: 60,
    shows: [],
    confirmation: 'replace',
    conditions: { join: 'and', groups: [], name: '', enabled: true },
    canvas: defaultCanvas(),
});

export const defaultRule = (type: ConditionType = 'pages'): ConditionRule => {
    if (type === 'visit') {
        return { type, operator: 'visited', value: 3 };
    }

    if (type === 'devices') {
        return { type, operator: 'equals', value: 'mobile' };
    }

    const operator = OPERATORS[type].includes('equals')
        ? 'equals'
        : OPERATORS[type][0];

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
