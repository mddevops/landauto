import { Head, Link } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import { useMemo, useState } from 'react';
import { blockAnchor } from '@/blocks/actions';
import type { DesignTokens } from '@/blocks/design';
import { FormView } from '@/blocks/form';
import { PopupView } from '@/blocks/popup';
import type { PopupRuntime } from '@/blocks/popup';
import { blockRenderer } from '@/blocks/registry';
import { BlockRenderContext } from '@/blocks/render-context';
import type { BlockState } from '@/blocks/state';
import { SiteTheme } from '@/blocks/theme';
import { TriggerScope } from '@/blocks/trigger-context';
import type { TriggerContextValue } from '@/blocks/trigger-context';
import type { VehicleBinding } from '@/blocks/vehicles';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { designer, preview } from '@/routes/sites';

type PreviewProps = {
    site: { public_id: string; name: string };
    page: { public_id: string; title: string };
    pages: { public_id: string; title: string }[];
    design: DesignTokens;
    blocks: {
        public_id: string;
        slug: string;
        name: string;
        state: BlockState;
    }[];
    assets: { public_id: string; url: string }[];
    vehicles: VehicleBinding[];
    popups: PopupRuntime[];
};

type OpenedPopup = {
    popup: PopupRuntime;
    context: TriggerContextValue;
    trigger: HTMLElement;
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
}: PreviewProps) {
    const [opened, setOpened] = useState<OpenedPopup | null>(null);
    const renderContext = useMemo(() => {
        const urls = new Map(
            assets.map((asset) => [asset.public_id, asset.url]),
        );
        const pageIds = new Set(pages.map((sitePage) => sitePage.public_id));
        const vehicleMap = new Map(
            vehicles.map((vehicle) => [vehicle.public_id, vehicle]),
        );
        const popupMap = new Map(
            popups.map((popup) => [popup.public_id, popup]),
        );

        return {
            assetUrl: (id: string) => urls.get(id) ?? null,
            pageHref: (id: string) =>
                pageIds.has(id)
                    ? preview.url(site.public_id, { query: { page: id } })
                    : null,
            vehicle: (id: string) => vehicleMap.get(id) ?? null,
            vehicles,
            hasPopup: (id: string) => popupMap.has(id),
            openPopup: (
                id: string,
                context: TriggerContextValue,
                trigger: HTMLElement,
            ) => {
                const popup = popupMap.get(id);

                if (popup) {
                    setOpened({ popup, context, trigger });
                }
            },
        };
    }, [assets, pages, vehicles, popups, site.public_id]);

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
                                query: { page: page.public_id },
                            })}
                        >
                            <ArrowLeft aria-hidden="true" />
                            Вернуться в дизайнер
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
                                                {Renderer ? (
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
            {opened && (
                <PopupView
                    popup={opened.popup}
                    tokens={design}
                    open
                    onOpenChange={(open) => !open && setOpened(null)}
                    returnFocusTo={opened.trigger}
                >
                    {opened.popup.form && <FormView form={opened.popup.form} />}
                </PopupView>
            )}
        </>
    );
}
