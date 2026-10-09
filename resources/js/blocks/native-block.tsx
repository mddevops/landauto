import { useCallback, useEffect, useMemo, useRef } from 'react';
import type { MouseEvent } from 'react';
import { runBlockAction } from '@/blocks/actions';
import { useBlockRenderContext } from '@/blocks/render-context';
import type { BlockState } from '@/blocks/state';
import { useTriggerContext } from '@/blocks/trigger-context';

/**
 * Compiled public output of a Native Block Instance (ADR-009), produced at Publish by the
 * backend Native compiler from an immutable Block Version and validated Instance state.
 */
export type NativeOutput = {
    scope: string;
    html: string;
    actions: string[];
    script?: string;
};

type NativeModule = {
    mount: (
        scope: string,
        root: HTMLElement,
        props: Readonly<BlockState>,
        api: Readonly<{ action: (key: string, trigger?: HTMLElement) => void }>,
    ) => unknown;
};
const modules = new Map<string, Promise<NativeModule>>();
function loadModule(url: string): Promise<NativeModule> {
    let promise = modules.get(url);
    if (!promise) {
        promise = import(/* @vite-ignore */ url) as Promise<NativeModule>;
        modules.set(url, promise);
    }
    return promise;
}

function deepFreeze<T>(value: T): T {
    if (
        value !== null &&
        typeof value === 'object' &&
        !Object.isFrozen(value)
    ) {
        Object.freeze(value);
        Object.values(value).forEach(deepFreeze);
    }
    return value;
}

/**
 * Renders a Native Block on the published Site as host DOM inside one controlled root. The
 * root carries the version scope its compiled CSS is bound to. Clicks on
 * `[data-landflow-action]` inside this root resolve against this Instance's own state only.
 */
export function NativeBlock({
    instance,
    slug,
    native,
    state,
}: {
    instance: string;
    slug: string;
    native: NativeOutput;
    state: BlockState;
}) {
    const context = useBlockRenderContext();
    const trigger = useTriggerContext();
    const root = useRef<HTMLDivElement>(null);

    const onClick = useCallback(
        (event: MouseEvent<HTMLDivElement>) => {
            const target = event.target;

            if (!(target instanceof Element)) {
                return;
            }

            const element = target.closest('[data-landflow-action]');

            if (
                !(element instanceof HTMLElement) ||
                element.closest('[data-landflow-native]') !==
                    event.currentTarget
            ) {
                return;
            }

            const key = element.getAttribute('data-landflow-action') ?? '';

            if (!native.actions.includes(key)) {
                return;
            }

            event.preventDefault();
            runBlockAction(state[key], context, trigger, element);
        },
        [native.actions, state, context, trigger],
    );

    const api = useMemo(
        () =>
            Object.freeze({
                action: (key: string, element?: HTMLElement) => {
                    const current = root.current;
                    if (!current || !native.actions.includes(key)) return;
                    const safeTrigger =
                        element instanceof HTMLElement &&
                        current.contains(element)
                            ? element
                            : current;
                    runBlockAction(state[key], context, trigger, safeTrigger);
                },
            }),
        [native.actions, state, context, trigger],
    );

    useEffect(() => {
        if (!native.script || !root.current) return;
        let active = true;
        let cleanup: (() => void) | undefined;
        const element = root.current;
        void loadModule(native.script)
            .then((module) => {
                if (!active) return;
                try {
                    const result = module.mount(
                        native.scope,
                        element,
                        deepFreeze(structuredClone(state)),
                        api,
                    );
                    if (typeof result === 'function')
                        cleanup = result as () => void;
                } catch {
                    /* one instance must not break the page */
                }
            })
            .catch(() => undefined);
        return () => {
            active = false;
            try {
                cleanup?.();
            } catch {
                /* isolate cleanup failure */
            }
        };
    }, [native.script, native.scope, state, api]);

    return (
        <div
            ref={root}
            data-landflow-native={native.scope}
            data-landflow-block={slug}
            data-landflow-instance={instance}
            onClick={onClick}
            // Only trusted Native compiler output from a Published Version (SECURITY.md §Native HTML).
            dangerouslySetInnerHTML={{ __html: native.html }}
        />
    );
}
