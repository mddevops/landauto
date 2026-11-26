import { useCallback, useEffect, useRef, useState } from 'react';
import type { PopupRuntime } from '@/blocks/popup';
import type { TriggerContextValue } from '@/blocks/trigger-context';
import {
    autoShowDelays,
    defaultLaunch,
    firstMatchingAutoPopup,
    markAutoShown,
} from '@/popups/launch';

export type OpenedPopup = {
    popup: PopupRuntime;
    context: TriggerContextValue;
    trigger: HTMLElement | null;
    source: 'auto' | 'manual';
};

export function useSitePopups({
    popups,
    siteId,
    onOpen,
}: {
    popups: PopupRuntime[];
    siteId: string;
    onOpen?: () => void;
}) {
    const [opened, setOpened] = useState<OpenedPopup | null>(null);
    const openedRef = useRef(opened);
    openedRef.current = opened;

    const openManual = useCallback(
        (id: string, context: TriggerContextValue, trigger: HTMLElement) => {
            const popup = popups.find((item) => item.public_id === id);

            if (!popup) {
                return;
            }

            setOpened({ popup, context, trigger, source: 'manual' });
            onOpen?.();
        },
        [popups, onOpen],
    );

    const close = useCallback(() => setOpened(null), []);

    useEffect(() => {
        const candidate = firstMatchingAutoPopup(popups, siteId);

        if (!candidate) {
            return;
        }

        const timers = autoShowDelays(candidate.launch ?? defaultLaunch()).map(
            (delay) =>
                window.setTimeout(() => {
                    if (openedRef.current !== null) {
                        return;
                    }

                    if (
                        firstMatchingAutoPopup(popups, siteId)?.public_id !==
                        candidate.public_id
                    ) {
                        return;
                    }

                    const launch = candidate.launch ?? defaultLaunch();
                    markAutoShown(launch, candidate.public_id);
                    setOpened({
                        popup: candidate,
                        context: {},
                        trigger: null,
                        source: 'auto',
                    });
                    onOpen?.();
                }, delay),
        );

        return () => timers.forEach((timer) => window.clearTimeout(timer));
    }, [popups, siteId, onOpen]);

    return { opened, openManual, close };
}
