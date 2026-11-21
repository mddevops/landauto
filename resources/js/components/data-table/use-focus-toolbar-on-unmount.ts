import * as React from 'react';

/** If focus is inside `node`, moves it to the toolbar instead of letting it fall to the page. */
export function focusToolbarFrom(node: HTMLElement | null) {
    if (!node?.contains(document.activeElement)) return;
    node.closest<HTMLElement>('[data-slot=data-table-toolbar]')
        // The toolbar may be far above a floating control.
        ?.focus({ preventScroll: true });
}

/** For toolbar controls that go away, like clear filters. */
export function useFocusToolbarOnUnmount<T extends HTMLElement>() {
    const ref = React.useRef<T>(null);
    React.useLayoutEffect(() => {
        const node = ref.current;
        return () => focusToolbarFrom(node);
    }, []);
    return ref;
}
