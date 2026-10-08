import { useCallback, useMemo } from 'react';
import { actionHref, actionPopupId } from '@/blocks/actions';
import { useBlockRenderContext } from '@/blocks/render-context';
import type { SchemaField } from '@/blocks/schema';
import type { BlockState } from '@/blocks/state';
import { useTriggerContext } from '@/blocks/trigger-context';
import { SandboxFrame } from '@/components/sandbox/sandbox-frame';
import { instanceProps } from '@/sandbox/instance-props';
import { actionKeys, PLACEHOLDER_IMAGE } from '@/sandbox/preview-data';

/** Published Studio code of a sandboxed Block Version, as delivered by the backend. */
export type SandboxSource = {
    name: string;
    html: string;
    css: string;
    js: string;
    fields: SchemaField[];
};

/**
 * Renders a sandboxed Block Version only through the ADR-008 wrapper. Block code receives the
 * Instance state as props and may request one of its own top-level action fields; the host
 * resolves that action with the regular Action System.
 */
export function SandboxedBlock({
    source,
    state,
    hostImages = false,
}: {
    source: SandboxSource;
    state: BlockState;
    /** Published Sites serve assets publicly from the host; draft assets need a session. */
    hostImages?: boolean;
}) {
    const context = useBlockRenderContext();
    const trigger = useTriggerContext();
    const sources = useMemo(
        () => ({ html: source.html, css: source.css, js: source.js }),
        [source.html, source.css, source.js],
    );
    const actions = useMemo(() => actionKeys(source.fields), [source.fields]);
    const props = useMemo(
        () =>
            instanceProps(source.fields, state, (id) =>
                hostImages
                    ? (context.assetUrl(id) ?? PLACEHOLDER_IMAGE)
                    : PLACEHOLDER_IMAGE,
            ),
        [source.fields, state, hostImages, context],
    );

    const runAction = useCallback(
        (key: string, frame: HTMLIFrameElement) => {
            const action = state[key];
            const popupId = actionPopupId(action);

            if (
                popupId !== null &&
                context.openPopup !== null &&
                context.hasPopup(popupId)
            ) {
                context.openPopup(popupId, trigger, frame);

                return;
            }

            const href = actionHref(action, context);

            if (href === null) {
                return;
            }

            if (href.startsWith('http')) {
                window.open(href, '_blank', 'noopener,noreferrer');
            } else if (href.startsWith('#')) {
                window.location.hash = href;
            } else {
                window.location.assign(href);
            }
        },
        [state, context, trigger],
    );

    return (
        <SandboxFrame
            title={source.name}
            sources={sources}
            props={props}
            actions={actions}
            hostImages={hostImages}
            onAction={runAction}
            className="block w-full border-0"
        />
    );
}
