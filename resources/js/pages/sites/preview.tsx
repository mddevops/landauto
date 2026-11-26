import { Head, Link } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import { useMemo } from 'react';
import { blockAnchor } from '@/blocks/actions';
import type { CaptchaConfig } from '@/blocks/captcha';
import type { CatalogProps, PageContext } from '@/blocks/catalog';
import { catalogPath, runtimeCatalog } from '@/blocks/catalog';
import type { DesignTokens } from '@/blocks/design';
import type { PopupRuntime } from '@/blocks/popup';
import { blockRenderer } from '@/blocks/registry';
import { BlockRenderContext } from '@/blocks/render-context';
import { SandboxedBlock } from '@/blocks/sandboxed-block';
import type { SandboxSource } from '@/blocks/sandboxed-block';
import type { BlockState } from '@/blocks/state';
import { SiteTheme } from '@/blocks/theme';
import { TriggerScope } from '@/blocks/trigger-context';
import type { VehicleBinding } from '@/blocks/vehicles';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { FloatingLauncherButton } from '@/popups/floating-launcher-button';
import { defaultLaunch } from '@/popups/launch';
import type { FloatingLauncher } from '@/popups/launcher';
import { PopupFormLayer } from '@/popups/popup-form-layer';
import { useSitePopups } from '@/popups/use-site-popups';
import { designer, preview } from '@/routes/sites';
import { store as previewSubmission } from '@/routes/sites/preview/submissions';

type PreviewProps = {
    site: { public_id: string; name: string };
    page: { public_id: string; title: string };
    pages: { public_id: string; title: string }[];
    design: DesignTokens;
    blocks: {
        public_id: string;
        slug: string;
        name: string;
        sandbox: SandboxSource | null;
        state: BlockState;
    }[];
    assets: { public_id: string; url: string }[];
    vehicles: VehicleBinding[];
    popups: PopupRuntime[];
    launcher: FloatingLauncher | null;
    captcha: CaptchaConfig | null;
    catalog: CatalogProps | null;
    pageContext: PageContext | null;
};

export default function Preview({
    site,
    page,
    pages,
    design,
    blocks,
    assets,
    vehicles,
    popups,
    launcher,
    captcha,
    catalog,
    pageContext,
}: PreviewProps) {
    const runtimePopups = useMemo(
        () =>
            popups.map((popup) => ({
                ...popup,
                launch: popup.launch ?? defaultLaunch(),
            })),
        [popups],
    );
    const { opened, openManual, close } = useSitePopups({
        popups: runtimePopups,
        siteId: site.public_id,
    });
    const renderContext = useMemo(() => {
        const urls = new Map(
            assets.map((asset) => [asset.public_id, asset.url]),
        );
        const pageIds = new Set(pages.map((sitePage) => sitePage.public_id));
        const vehicleMap = new Map(
            vehicles.map((vehicle) => [vehicle.public_id, vehicle]),
        );
        const popupIds = new Set(runtimePopups.map((popup) => popup.public_id));

        return {
            assetUrl: (id: string) => urls.get(id) ?? null,
            pageHref: (id: string) =>
                catalog && catalog.pages.index === id
                    ? preview.url(site.public_id, {
                          query: { path: catalog.prefix },
                      })
                    : pageIds.has(id)
                      ? preview.url(site.public_id, { query: { page: id } })
                      : null,
            vehicle: (id: string) => vehicleMap.get(id) ?? null,
            vehicles,
            hasPopup: (id: string) => popupIds.has(id),
            openPopup: openManual,
            catalog: runtimeCatalog(catalog, (current, target) => {
                const path = catalogPath(current, target);

                return path
                    ? preview.url(site.public_id, { query: { path } })
                    : null;
            }),
            pageContext,
        };
    }, [
        assets,
        pages,
        vehicles,
        runtimePopups,
        site.public_id,
        catalog,
        pageContext,
        openManual,
    ]);
    const sample =
        pageContext?.vehicle ??
        catalog?.vehicles.find((vehicle) => {
            if (pageContext?.model) {
                return (
                    vehicle.mark.segment === pageContext.mark &&
                    vehicle.model.segment === pageContext.model
                );
            }

            return (
                pageContext?.mark !== null &&
                vehicle.mark.segment === pageContext?.mark
            );
        })?.public_id ??
        null;

    return (
        <>
            <Head title={`Предпросмотр — ${page.title}`}>
                <meta name="robots" content="noindex, nofollow" />
            </Head>
            <div className="flex min-h-svh flex-col bg-white">
                <div className="flex items-center gap-3 border-b bg-neutral-50 px-3 py-2 text-sm sm:px-4">
                    <Button asChild variant="ghost" size="sm">
                        <Link
                            href={designer(site.public_id, {
                                query: {
                                    page: page.public_id,
                                    ...(sample ? { sample } : {}),
                                },
                            })}
                        >
                            <ArrowLeft aria-hidden="true" />
                            <span className="max-sm:sr-only">
                                Вернуться в дизайнер
                            </span>
                        </Link>
                    </Button>
                    <p className="min-w-0 flex-1 truncate text-muted-foreground">
                        {`${site.name} · ${page.title}`}
                    </p>
                    <Badge variant="outline">Предпросмотр черновика</Badge>
                </div>
                <main aria-label="Предпросмотр страницы" className="flex-1">
                    <BlockRenderContext value={renderContext}>
                        <SiteTheme tokens={design}>
                            {blocks.length === 0 ? (
                                <p className="p-10 text-center text-sm text-neutral-500">
                                    На странице пока нет видимых блоков.
                                </p>
                            ) : (
                                blocks.map((block) => {
                                    const Renderer = blockRenderer(block.slug);

                                    return (
                                        <div
                                            key={block.public_id}
                                            id={blockAnchor(block.public_id)}
                                        >
                                            <TriggerScope
                                                value={{
                                                    block: block.public_id,
                                                }}
                                            >
                                                {block.sandbox ? (
                                                    <SandboxedBlock
                                                        source={block.sandbox}
                                                        state={block.state}
                                                    />
                                                ) : Renderer ? (
                                                    <Renderer
                                                        state={block.state}
                                                    />
                                                ) : null}
                                            </TriggerScope>
                                        </div>
                                    );
                                })
                            )}
                        </SiteTheme>
                    </BlockRenderContext>
                </main>
            </div>
            {launcher?.enabled && (
                <FloatingLauncherButton
                    launcher={launcher}
                    onOpenPopup={(trigger) => {
                        if (launcher.popup) {
                            openManual(launcher.popup, {}, trigger);
                        }
                    }}
                />
            )}
            {opened && (
                <PopupFormLayer
                    opened={opened}
                    design={design}
                    pagePublicId={page.public_id}
                    formAction={(formId) =>
                        previewSubmission.url({
                            site: site.public_id,
                            form: formId,
                        })
                    }
                    captcha={captcha}
                    authenticated
                    onClose={close}
                />
            )}
        </>
    );
}
