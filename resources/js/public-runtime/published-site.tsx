import { useMemo, useState } from 'react';
import { blockAnchor } from '@/blocks/actions';
import type { CaptchaConfig } from '@/blocks/captcha';
import type { DesignTokens } from '@/blocks/design';
import { FormView } from '@/blocks/form';
import { currentTracking, submitForm } from '@/blocks/form-submit';
import { PopupView } from '@/blocks/popup';
import type { PopupRuntime } from '@/blocks/popup';
import { blockRenderer } from '@/blocks/registry';
import { BlockRenderContext } from '@/blocks/render-context';
import type { BlockRenderContextValue } from '@/blocks/render-context';
import type { BlockState } from '@/blocks/state';
import { SiteTheme } from '@/blocks/theme';
import { TriggerScope } from '@/blocks/trigger-context';
import type { TriggerContextValue } from '@/blocks/trigger-context';
import type { VehicleBinding } from '@/blocks/vehicles';
import { track } from './analytics';

/**
 * Exact public payload of one published Page. The publish-time renderer and the browser
 * hydration receive the same object, built only from the Published Version manifest.
 */
export type PublishedPagePayload = {
    version: string;
    site: { name: string };
    page: { public_id: string; title: string };
    pages: { public_id: string; path: string }[];
    design: DesignTokens;
    blocks: { public_id: string; slug: string; state: BlockState }[];
    assets: { public_id: string; url: string }[];
    vehicles: VehicleBinding[];
    popups: PopupRuntime[];
    branding: boolean;
    form_action: string;
};

type OpenedPopup = {
    popup: PopupRuntime;
    context: TriggerContextValue;
    trigger: HTMLElement;
};

export function PublishedSite({
    payload,
    captcha = null,
}: {
    payload: PublishedPagePayload;
    /** Live widget config added in the browser only; Popups are closed during SSR. */
    captcha?: CaptchaConfig | null;
}) {
    const [opened, setOpened] = useState<OpenedPopup | null>(null);
    const renderContext = useMemo<BlockRenderContextValue>(() => {
        const urls = new Map(
            payload.assets.map((asset) => [asset.public_id, asset.url]),
        );
        const paths = new Map(
            payload.pages.map((page) => [page.public_id, page.path]),
        );
        const vehicles = new Map(
            payload.vehicles.map((vehicle) => [vehicle.public_id, vehicle]),
        );
        const popups = new Map(
            payload.popups.map((popup) => [popup.public_id, popup]),
        );

        return {
            assetUrl: (id) => urls.get(id) ?? null,
            pageHref: (id) => paths.get(id) ?? null,
            vehicle: (id) => vehicles.get(id) ?? null,
            vehicles: payload.vehicles,
            hasPopup: (id) => popups.has(id),
            openPopup: (id, context, trigger) => {
                const popup = popups.get(id);

                if (popup) {
                    setOpened({ popup, context, trigger });
                    track('popup.open');
                }
            },
        };
    }, [payload]);
    const openedForm = opened?.popup.form ?? null;

    return (
        <>
            <main>
                <BlockRenderContext value={renderContext}>
                    <SiteTheme tokens={payload.design}>
                        {payload.blocks.map((block) => {
                            const Renderer = blockRenderer(block.slug);

                            return (
                                <div
                                    key={block.public_id}
                                    id={blockAnchor(block.public_id)}
                                >
                                    <TriggerScope
                                        value={{ block: block.public_id }}
                                    >
                                        {Renderer ? (
                                            <Renderer state={block.state} />
                                        ) : null}
                                    </TriggerScope>
                                </div>
                            );
                        })}
                    </SiteTheme>
                </BlockRenderContext>
            </main>
            {payload.branding && (
                <p
                    data-testid="landflow-branding"
                    className="border-t border-neutral-200 bg-white px-4 py-3 text-center text-xs text-neutral-500"
                >
                    Сайт создан на Landflow
                </p>
            )}
            {opened && (
                <PopupView
                    popup={opened.popup}
                    tokens={payload.design}
                    open
                    onOpenChange={(open) => {
                        if (!open) {
                            setOpened(null);
                            track('popup.close');
                        }
                    }}
                    returnFocusTo={opened.trigger}
                >
                    {openedForm && (
                        <FormView
                            form={openedForm}
                            captcha={captcha}
                            onEvent={(event) =>
                                track(
                                    event === 'start'
                                        ? 'form.start'
                                        : 'form.validation_error',
                                )
                            }
                            onSubmit={async (values, meta) => {
                                track('form.submit');

                                if (opened.context.vehicle) {
                                    track('vehicle.form_submit');
                                }

                                const result = await submitForm(
                                    `${payload.form_action}/${openedForm.public_id}`,
                                    {
                                        fields: values,
                                        lf_hp: meta.honeypot,
                                        ...(meta.captchaToken !== null && {
                                            captcha_token: meta.captchaToken,
                                        }),
                                        context: {
                                            ...opened.context,
                                            page: payload.page.public_id,
                                            popup: opened.popup.public_id,
                                        },
                                        tracking: currentTracking(),
                                    },
                                );

                                if (result.ok) {
                                    track('form.success');
                                }

                                return result;
                            }}
                        />
                    )}
                </PopupView>
            )}
        </>
    );
}
