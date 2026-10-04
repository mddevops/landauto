import type { BlockRenderContextValue } from '@/blocks/render-context';
import type { BlockState } from '@/blocks/state';

export const actionTypes = {
    open_url: { label: 'Открыть ссылку', target: 'url' },
    open_page: { label: 'Открыть страницу', target: 'page' },
    scroll_to: { label: 'Прокрутить к блоку', target: 'block' },
    phone: { label: 'Позвонить', target: 'phone' },
    email: { label: 'Написать на email', target: 'email' },
    open_popup: { label: 'Открыть попап', target: 'popup' },
} as const;

export type ActionType = keyof typeof actionTypes;

export function isActionType(value: unknown): value is ActionType {
    return typeof value === 'string' && Object.hasOwn(actionTypes, value);
}

export function blockAnchor(blockId: string): string {
    return `block-${blockId}`;
}

/**
 * Link target for a stored action. The backend already validated it; the scheme checks are
 * repeated here so a renderer can never emit a script URL.
 */
export function actionHref(
    action: unknown,
    context: BlockRenderContextValue,
): string | null {
    if (typeof action !== 'object' || action === null) {
        return null;
    }

    const state = action as BlockState;
    const type = state.type;

    if (!isActionType(type)) {
        return null;
    }

    const target = state[actionTypes[type].target];

    if (typeof target !== 'string' || target === '') {
        return null;
    }

    switch (type) {
        case 'open_url':
            return /^https?:\/\//i.test(target) ? target : null;
        case 'open_page':
            return context.pageHref(target);
        case 'scroll_to':
            return `#${blockAnchor(target)}`;
        case 'phone':
            return `tel:${target.replace(/[^\d+]/g, '')}`;
        case 'email':
            return `mailto:${target}`;
        case 'open_popup':
            return null;
    }
}

/** Popup public ID of an `open_popup` action, if any; opening is done by the runtime. */
export function actionPopupId(action: unknown): string | null {
    if (typeof action !== 'object' || action === null) {
        return null;
    }

    const state = action as BlockState;

    return state.type === 'open_popup' &&
        typeof state.popup === 'string' &&
        state.popup !== ''
        ? state.popup
        : null;
}
