import type { FormDataConvertible } from '@inertiajs/core';
import { router } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import type { BlockState } from '@/blocks/state';
import { state as stateUrl } from '@/routes/sites/blocks';

export type AutosaveStatus = 'idle' | 'pending' | 'saving' | 'saved' | 'error';

const DELAY_MS = 700;

/**
 * Debounced draft autosave of Block state. One request at a time; edits made while a request
 * is in flight are saved afterwards. Saving only touches draft state, never publishing.
 */
export function useBlockAutosave(siteId: string) {
    const [drafts, setDrafts] = useState<Record<string, BlockState>>({});
    const [status, setStatus] = useState<AutosaveStatus>('idle');
    const latest = useRef<Record<string, BlockState>>({});
    const timers = useRef(new Map<string, number>());
    const queue = useRef<string[]>([]);
    const inFlight = useRef(false);
    const failed = useRef(false);

    const commit = (next: Record<string, BlockState>) => {
        latest.current = next;
        setDrafts(next);
    };

    const schedule = (blockId: string) => {
        window.clearTimeout(timers.current.get(blockId));
        timers.current.set(
            blockId,
            window.setTimeout(() => {
                timers.current.delete(blockId);

                if (!queue.current.includes(blockId)) {
                    queue.current.push(blockId);
                }

                pump();
            }, DELAY_MS),
        );
    };

    const pump = () => {
        if (inFlight.current) {
            return;
        }

        const blockId = queue.current.shift();

        if (blockId === undefined) {
            setStatus(
                failed.current
                    ? 'error'
                    : timers.current.size > 0
                      ? 'pending'
                      : 'saved',
            );

            return;
        }

        const draft = latest.current[blockId];

        if (draft === undefined) {
            pump();

            return;
        }

        inFlight.current = true;
        let succeeded = false;
        let cancelled = false;
        setStatus('saving');

        router.patch(
            stateUrl.url({ site: siteId, block: blockId }),
            { state: draft as FormDataConvertible },
            {
                async: true,
                preserveScroll: true,
                preserveState: true,
                onSuccess: () => {
                    succeeded = true;

                    if (latest.current[blockId] === draft) {
                        const rest = { ...latest.current };
                        delete rest[blockId];
                        commit(rest);
                    }
                },
                onCancel: () => {
                    cancelled = true;
                },
                onFinish: () => {
                    inFlight.current = false;

                    if (cancelled) {
                        schedule(blockId);
                    } else {
                        failed.current = !succeeded;
                    }

                    pump();
                },
            },
        );
    };

    const update = (blockId: string, state: BlockState) => {
        commit({ ...latest.current, [blockId]: state });
        failed.current = false;
        setStatus('pending');
        schedule(blockId);
    };

    const hasUnsaved = Object.keys(drafts).length > 0;

    useEffect(() => {
        if (!hasUnsaved) {
            return;
        }

        const warn = (event: BeforeUnloadEvent) => event.preventDefault();
        window.addEventListener('beforeunload', warn);

        return () => window.removeEventListener('beforeunload', warn);
    }, [hasUnsaved]);

    useEffect(() => {
        const pending = timers.current;

        return () => pending.forEach((timer) => window.clearTimeout(timer));
    }, []);

    return { drafts, status, update };
}
