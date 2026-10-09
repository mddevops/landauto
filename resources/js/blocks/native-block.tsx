import { useCallback } from 'react';
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
};

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

    return (
        <div
            data-landflow-native={native.scope}
            data-landflow-block={slug}
            data-landflow-instance={instance}
            onClick={onClick}
            // Only trusted Native compiler output from a Published Version (SECURITY.md §Native HTML).
            dangerouslySetInnerHTML={{ __html: native.html }}
        />
    );
}
