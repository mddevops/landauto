import * as React from 'react';
import type { DataTableInstance, DataTableRow } from '@querycn/table-react';

import { getTableContainer } from '@/components/data-table/column-layout-actions';
import { getSelectedPageRows } from '@/components/data-table/data-table-pagination-state';
import { focusToolbarFrom } from '@/components/data-table/use-focus-toolbar-on-unmount';

const EDGE = 8;
const GAP = 16;

/**
 * The selection bar's state: mounted while rows are selected and during its
 * exit animation, which still shows the last rows. While mounted, it floats
 * centred over the table, above the table's bottom edge or the viewport's.
 */
export function useFloatingSelectionBar<TData extends object>(
    table: DataTableInstance<TData>,
) {
    const ref = React.useRef<HTMLDivElement>(null);
    const rows = getSelectedPageRows(table);
    const open = rows.length > 0;

    const key = rows.map((row) => row.id).join('\n');
    const [last, setLast] = React.useState({ key, rows });
    if (open && key !== last.key) setLast({ key, rows });

    const [mounted, setMounted] = React.useState(open);
    if (open && !mounted) setMounted(true);
    const closing = mounted && !open;

    React.useLayoutEffect(() => {
        const node = ref.current;
        if (!closing || !node) return;
        focusToolbarFrom(node);
        const name = getComputedStyle(node).animationName;
        // No exit animation (reduced motion, tests): nothing to wait for.
        if (!name || name === 'none') return setMounted(false);
        const done = (event?: AnimationEvent) => {
            if (!event || event.target === node) setMounted(false);
        };
        const timer = setTimeout(done, 400);
        node.addEventListener('animationend', done);
        return () => {
            clearTimeout(timer);
            node.removeEventListener('animationend', done);
        };
    }, [closing]);

    // `useDataTable` returns a new table each render, and `DataTable` registers
    // its container under it after this hook's effects: look it up when placing.
    const tableRef = React.useRef(table);
    React.useLayoutEffect(() => {
        tableRef.current = table;
    });

    React.useLayoutEffect(() => {
        const bar = ref.current;
        if (!mounted || !bar) return;
        const observer = new ResizeObserver(() => schedule());
        let observed: HTMLElement | undefined;
        let frame = 0;
        const place = () => {
            frame = 0;
            const container = getTableContainer(tableRef.current);
            if (container && container !== observed) {
                if (observed) observer.unobserve(observed);
                observer.observe((observed = container));
            }
            const viewport = document.documentElement;
            const rect = container?.getBoundingClientRect();
            const half = bar.offsetWidth / 2;
            const centre = rect
                ? rect.left + rect.width / 2
                : viewport.clientWidth / 2;
            const x = Math.min(
                Math.max(centre, EDGE + half),
                viewport.clientWidth - EDGE - half,
            );
            const bottom = rect
                ? Math.max(GAP, viewport.clientHeight - rect.bottom + GAP)
                : GAP;
            bar.style.setProperty('--bar-x', `${x}px`);
            bar.style.setProperty('--bar-bottom', `${bottom}px`);
        };
        const schedule = () => {
            if (!frame) frame = requestAnimationFrame(place);
        };
        place();
        // Again once `DataTable` has registered its container; the bar is still
        // transparent then, at the start of its enter animation.
        schedule();
        observer.observe(bar);
        window.addEventListener('scroll', schedule, {
            capture: true,
            passive: true,
        });
        window.addEventListener('resize', schedule);
        return () => {
            cancelAnimationFrame(frame);
            observer.disconnect();
            window.removeEventListener('scroll', schedule, { capture: true });
            window.removeEventListener('resize', schedule);
        };
    }, [mounted]);

    const shown: DataTableRow<TData>[] = open ? rows : last.rows;
    return { ref, mounted, closing, rows: shown };
}
