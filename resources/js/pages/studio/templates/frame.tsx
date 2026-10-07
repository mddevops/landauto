import { Head } from '@inertiajs/react';
import { useMemo } from 'react';
import type { DesignTokens } from '@/blocks/design';
import { BlockRenderContext } from '@/blocks/render-context';
import { SiteTheme } from '@/blocks/theme';
import { RenderedBlock } from '@/components/designer/canvas';
import type { DesignerBlock } from '@/components/designer/types';

/** Bare Template Draft page rendered inside the preview iframe, so device widths hit real breakpoints. */
export default function TemplateFrame({
    page,
    design,
    blocks,
}: {
    page: { public_id: string; title: string };
    design: DesignTokens;
    blocks: DesignerBlock[];
}) {
    const renderContext = useMemo(
        () => ({
            assetUrl: () => null,
            pageHref: () => null,
            vehicle: () => null,
            vehicles: [],
            hasPopup: () => false,
            openPopup: null,
        }),
        [],
    );

    return (
        <>
            <Head title={page.title}>
                <meta name="robots" content="noindex, nofollow" />
            </Head>
            <main aria-label="Страница шаблона" className="min-h-svh bg-white">
                <BlockRenderContext value={renderContext}>
                    <SiteTheme tokens={design}>
                        {blocks.length === 0 ? (
                            <p className="p-10 text-center text-sm text-neutral-500">
                                На странице пока нет видимых блоков.
                            </p>
                        ) : (
                            blocks.map((block) => (
                                <RenderedBlock
                                    key={block.public_id}
                                    block={block}
                                    state={block.state}
                                />
                            ))
                        )}
                    </SiteTheme>
                </BlockRenderContext>
            </main>
        </>
    );
}
