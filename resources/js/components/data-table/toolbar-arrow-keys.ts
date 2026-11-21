import type * as React from 'react';

const FOCUSABLE =
    'button:not(:disabled), a[href], input:not(:disabled), [tabindex]:not([tabindex="-1"])';

/**
 * Arrow keys, Home and End move focus between a toolbar's controls, mirrored
 * right to left. Tab still reaches each one: the controls can be anything.
 */
export function onToolbarArrowKeys(event: React.KeyboardEvent<HTMLElement>) {
    const { key, currentTarget: toolbar } = event;
    if (!['ArrowLeft', 'ArrowRight', 'Home', 'End'].includes(key)) return;
    if ((event.target as HTMLElement).closest('input, textarea')) return;
    const items = Array.from(toolbar.querySelectorAll<HTMLElement>(FOCUSABLE));
    const index = items.indexOf(document.activeElement as HTMLElement);
    if (index === -1) return;
    const rtl = toolbar.closest('[dir]')?.getAttribute('dir') === 'rtl';
    const forward = (key === 'ArrowRight') !== rtl;
    const next =
        key === 'Home'
            ? 0
            : key === 'End'
              ? items.length - 1
              : (index + (forward ? 1 : -1) + items.length) % items.length;
    event.preventDefault();
    items[next]!.focus();
}
