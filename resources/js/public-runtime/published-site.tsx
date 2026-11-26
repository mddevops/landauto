import { useMemo } from 'react';
import { blockAnchor } from '@/blocks/actions';
import type { CaptchaConfig } from '@/blocks/captcha';
import { catalogPath, runtimeCatalog } from '@/blocks/catalog';
import type { CatalogProps, PageContext } from '@/blocks/catalog';
import type { DesignTokens } from '@/blocks/design';
import { NativeBlock } from '@/blocks/native-block';
import type { NativeOutput } from '@/blocks/native-block';
import type { PopupRuntime } from '@/blocks/popup';
import { blockRenderer } from '@/blocks/registry';
import { BlockRenderContext } from '@/blocks/render-context';
import type { BlockRenderContextValue } from '@/blocks/render-context';
import { SandboxedBlock } from '@/blocks/sandboxed-block';
import type { SandboxSource } from '@/blocks/sandboxed-block';
import type { BlockState } from '@/blocks/state';
import { SiteTheme } from '@/blocks/theme';
import { TriggerScope } from '@/blocks/trigger-context';
import type { VehicleBinding } from '@/blocks/vehicles';
import { FloatingLauncherButton } from '@/popups/floating-launcher-button';
import { defaultLaunch } from '@/popups/launch';
import type { FloatingLauncher } from '@/popups/launcher';
import { PopupFormLayer } from '@/popups/popup-form-layer';
import { useSitePopups } from '@/popups/use-site-popups';
import { track } from './analytics';

/**
 * Exact public payload of one published Page. The publish-time renderer and the browser
 * hydration receive the same object, built only from the Published Version manifest.
 */
export type PublishedPagePayload = {
    version: string;
    site: { name: string; public_id?: string };
    page: { public_id: string; title: string };
    pages: { public_id: string; path: string }[];
    /** Present while the catalog section is published; `base` prefixes its addresses. */
    catalog?: CatalogProps & { base: string };
    /** Present on generated section pages: the mark, model or vehicle the page shows. */
    page_context?: PageContext;
    design: DesignTokens;
    blocks: {
        public_id: string;
        slug: string;
        state: BlockState;
        sandbox?: SandboxSource;
        /** Compiled Native output only; Native source never reaches the browser. */
        native?: NativeOutput;
    }[];
    assets: { public_id: string; url: string }[];
    vehicles: VehicleBinding[];
    popups: PopupRuntime[];
    form_action: string;
    floating_launcher?: FloatingLauncher;
};

export function PublishedSite({
    payload,
    captcha = null,
}: {
    payload: PublishedPagePayload;
    /** Live widget config added in the browser only; Popups are closed during SSR. */
    captcha?: CaptchaConfig | null;
}) {
    const popups = useMemo(
        () =>
            payload.popups.map((popup) => ({
                ...popup,
                launch: popup.launch ?? defaultLaunch(),
            })),
        [payload.popups],
    );
    const { opened, openManual, close } = useSitePopups({
        popups,
        siteId: payload.site.public_id ?? payload.version,
        onOpen: () => track('popup.open'),
    });
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
        const popupIds = new Set(popups.map((popup) => popup.public_id));
        const base = payload.catalog?.base ?? '';

        return {
            catalog: runtimeCatalog(
                payload.catalog ?? null,
                (catalog, target) => {
                    const path = catalogPath(catalog, target);

                    return path === null ? null : `${base}/${path}`;
                },
            ),
            pageContext: payload.page_context ?? null,
            assetUrl: (id) => urls.get(id) ?? null,
            pageHref: (id) => paths.get(id) ?? null,
            vehicle: (id) => vehicles.get(id) ?? null,
            vehicles: payload.vehicles,
            hasPopup: (id) => popupIds.has(id),
            openPopup: openManual,
        };
    }, [payload, popups, openManual]);

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
                                        {block.native ? (
                                            <NativeBlock
                                                instance={block.public_id}
                                                slug={block.slug}
                                                native={block.native}
                                                state={block.state}
                                            />
                                        ) : block.sandbox ? (
                                            <SandboxedBlock
                                                source={block.sandbox}
                                                state={block.state}
                                                hostImages
                                            />
                                        ) : Renderer ? (
                                            <Renderer state={block.state} />
                                        ) : null}
                                    </TriggerScope>
                                </div>
                            );
                        })}
                    </SiteTheme>
                </BlockRenderContext>
            </main>
            {payload.floating_launcher?.enabled && (
                <FloatingLauncherButton
                    launcher={payload.floating_launcher}
                    onOpenPopup={(trigger) => {
                        if (payload.floating_launcher?.popup) {
                            openManual(
                                payload.floating_launcher.popup,
                                {},
                                trigger,
                            );
                        }
                    }}
                />
            )}
            {opened && (
                <PopupFormLayer
                    opened={opened}
                    design={payload.design}
                    pagePublicId={payload.page.public_id}
                    formAction={(formId) => `${payload.form_action}/${formId}`}
                    captcha={captcha}
                    onClose={() => {
                        close();
                        track('popup.close');
                    }}
                    onFormEvent={(event, context) => {
                        if (event === 'start') {
                            track('form.start');
                        }

                        if (event === 'validation_error') {
                            track('form.validation_error');
                        }

                        if (event === 'submit') {
                            track('form.submit');

                            if (context.vehicle) {
                                track('vehicle.form_submit');
                            }
                        }

                        if (event === 'success') {
                            track('form.success');
                        }
                    }}
                />
            )}
        </>
    );
}
