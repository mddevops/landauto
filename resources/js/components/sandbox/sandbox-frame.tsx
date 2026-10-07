import {
    useEffect,
    useMemo,
    useRef,
    useState,
    useSyncExternalStore,
} from 'react';
import { buildSandboxDocument, SANDBOX_ATTRIBUTE } from '@/sandbox/document';
import type { SandboxSources } from '@/sandbox/document';

const MIN_HEIGHT = 40;
const MAX_HEIGHT = 4000;

const noSubscription = () => () => {};

/** The host origin; unknown while rendering on the server, so no document is built there. */
function useHostOrigin(): string | null {
    return useSyncExternalStore(
        noSubscription,
        () => window.location.origin,
        () => null,
    );
}

type SandboxFrameProps = {
    title: string;
    sources: SandboxSources;
    props: Record<string, unknown>;
    actions: string[];
    /** Allow images from the host origin (public, cookie-free asset URLs of a published Site). */
    hostImages?: boolean;
    onError?: (message: string) => void;
    onAction?: (key: string, frame: HTMLIFrameElement) => void;
    className?: string;
};

/**
 * Renders authored Block code in an opaque-origin iframe (ADR-008). Messages are accepted only
 * from this frame's window, with the `null` origin and an allowlisted `landflow:*` type.
 */
export function SandboxFrame({
    title,
    sources,
    props,
    actions,
    hostImages = false,
    onError,
    onAction,
    className,
}: SandboxFrameProps) {
    const frame = useRef<HTMLIFrameElement>(null);
    const [height, setHeight] = useState(160);
    const handlers = useRef({ onError, onAction, actions });
    const origin = useHostOrigin();

    useEffect(() => {
        handlers.current = { onError, onAction, actions };
    }, [onError, onAction, actions]);

    const srcDoc = useMemo(
        () =>
            origin === null
                ? undefined
                : buildSandboxDocument(sources, {
                      props,
                      actions,
                      assetOrigin: hostImages ? origin : undefined,
                      parentOrigin: origin,
                  }),
        [sources, props, actions, hostImages, origin],
    );

    useEffect(() => {
        const listener = (event: MessageEvent) => {
            if (
                frame.current === null ||
                event.source !== frame.current.contentWindow ||
                event.origin !== 'null'
            ) {
                return;
            }

            const message: unknown = event.data;

            if (message === null || typeof message !== 'object') {
                return;
            }

            const {
                type,
                height: next,
                key,
                message: text,
            } = message as {
                type?: unknown;
                height?: unknown;
                key?: unknown;
                message?: unknown;
            };
            const current = handlers.current;

            if (
                type === 'landflow:resize' &&
                typeof next === 'number' &&
                Number.isFinite(next)
            ) {
                setHeight(
                    Math.min(MAX_HEIGHT, Math.max(MIN_HEIGHT, Math.ceil(next))),
                );
            } else if (
                type === 'landflow:action' &&
                typeof key === 'string' &&
                current.actions.includes(key)
            ) {
                current.onAction?.(key, frame.current);
            } else if (type === 'landflow:error' && typeof text === 'string') {
                current.onError?.(text.slice(0, 500));
            }
        };

        window.addEventListener('message', listener);

        return () => window.removeEventListener('message', listener);
    }, []);

    return (
        <iframe
            ref={frame}
            title={title}
            sandbox={SANDBOX_ATTRIBUTE}
            srcDoc={srcDoc}
            referrerPolicy="no-referrer"
            style={{ height }}
            className={className}
        />
    );
}
