import type { FormDataConvertible } from '@inertiajs/core';
import { router } from '@inertiajs/react';
import { useCallback, useEffect, useRef, useState } from 'react';
import type { PreviewData } from '@/sandbox/preview-data';
import type { BlockDraft, DraftSourceKey, DraftSources } from '@/types/blocks';

export type DraftSaveStatus =
    | 'saved'
    | 'pending'
    | 'saving'
    | 'error'
    | 'conflict';

type DraftContent = { sources: DraftSources; preview: PreviewData };

const DELAY_MS = 800;

/**
 * Debounced Draft autosave for Block Studio. One request at a time; edits made while a request is
 * in flight are saved afterwards. A revision conflict stops autosave until the page is reloaded,
 * so a newer save is never overwritten. Saving only touches the Draft, never publishing.
 */
export function useDraftAutosave(url: string, draft: BlockDraft) {
    const initial = { sources: draft.sources, preview: draft.preview };
    const [content, setContent] = useState<DraftContent>(initial);
    const [status, setStatus] = useState<DraftSaveStatus>('saved');
    const [errors, setErrors] = useState<Record<string, string>>({});
    const latest = useRef(initial);
    const saved = useRef(initial);
    const revision = useRef(draft.revision);
    const timer = useRef<number | undefined>(undefined);
    const inFlight = useRef(false);
    const stopped = useRef(false);

    useEffect(() => {
        revision.current = Math.max(revision.current, draft.revision);
    }, [draft.revision]);

    const save = useCallback(() => {
        window.clearTimeout(timer.current);

        if (inFlight.current || stopped.current) {
            return;
        }

        const payload = latest.current;

        if (payload === saved.current) {
            setStatus('saved');

            return;
        }

        inFlight.current = true;
        setStatus('saving');

        router.put(
            url,
            {
                revision: revision.current,
                sources: payload.sources,
                preview: payload.preview as FormDataConvertible,
            },
            {
                async: true,
                preserveScroll: true,
                preserveState: true,
                onSuccess: (page) => {
                    const next = page.props.draft as BlockDraft | undefined;

                    if (next) {
                        revision.current = next.revision;
                    }

                    saved.current = payload;
                    setErrors({});
                },
                onError: (failed) => {
                    setErrors(failed);

                    if (failed.draft) {
                        stopped.current = true;
                    }
                },
                onFinish: () => {
                    inFlight.current = false;

                    if (stopped.current) {
                        setStatus('conflict');
                    } else if (saved.current !== payload) {
                        setStatus('error');
                    } else if (latest.current !== payload) {
                        setStatus('pending');
                        timer.current = window.setTimeout(save, DELAY_MS);
                    } else {
                        setStatus('saved');
                    }
                },
            },
        );
    }, [url]);

    const commit = (next: DraftContent) => {
        latest.current = next;
        setContent(next);

        if (stopped.current) {
            return;
        }

        setStatus('pending');
        window.clearTimeout(timer.current);
        timer.current = window.setTimeout(save, DELAY_MS);
    };

    const update = (key: DraftSourceKey, value: string) =>
        commit({
            ...latest.current,
            sources: { ...latest.current.sources, [key]: value },
        });

    const updatePreview = (preview: PreviewData) =>
        commit({ ...latest.current, preview });

    const dirty = status !== 'saved';

    useEffect(() => {
        if (!dirty) {
            return;
        }

        const warn = (event: BeforeUnloadEvent) => event.preventDefault();
        window.addEventListener('beforeunload', warn);
        const removeBefore = router.on('before', (event) => {
            if (
                event.detail.visit.method === 'get' &&
                !window.confirm('Черновик ещё не сохранён. Покинуть студию?')
            ) {
                event.preventDefault();
            }
        });

        return () => {
            window.removeEventListener('beforeunload', warn);
            removeBefore();
        };
    }, [dirty]);

    useEffect(() => () => window.clearTimeout(timer.current), []);

    return {
        sources: content.sources,
        preview: content.preview,
        status,
        errors,
        update,
        updatePreview,
        saveNow: save,
    };
}
