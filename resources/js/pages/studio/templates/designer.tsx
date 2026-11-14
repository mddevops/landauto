import { Head, Link, router, usePage } from '@inertiajs/react';
import { ArrowLeft, Eye, Settings } from 'lucide-react';
import { useMemo, useState } from 'react';
import type { DesignTokens } from '@/blocks/design';
import { BlockRenderContext } from '@/blocks/render-context';
import { SiteTheme } from '@/blocks/theme';
import { AutosaveIndicator, CanvasBlock } from '@/components/designer/canvas';
import { DesignerContext } from '@/components/designer/designer-context';
import { DesignerSidebar } from '@/components/designer/designer-sidebar';
import { PagesPanel } from '@/components/designer/pages-panel';
import { PropertiesPanel } from '@/components/designer/properties-panel';
import type {
    DesignerBlock,
    DesignerBlockRoutes,
    DesignerLibraryBlock,
    DesignerPage,
    DesignerPageRoutes,
} from '@/components/designer/types';
import { useBlockAutosave } from '@/components/designer/use-block-autosave';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { designer, preview, show } from '@/routes/studio/templates';
import {
    destroy as destroyBlock,
    duplicate as duplicateBlock,
    move as moveBlock,
    state as blockState,
    store as storeBlock,
    visibility as blockVisibility,
    upgrade as upgradeBlock,
} from '@/routes/studio/templates/blocks';
import {
    destroy as destroyPage,
    store as storePage,
    update as updatePage,
} from '@/routes/studio/templates/pages';

type TemplateDesignerProps = {
    template: { public_id: string; name: string; owner_scope: string };
    design: DesignTokens;
    page: { public_id: string; title: string };
    pages: DesignerPage[];
    blocks: DesignerBlock[];
    selectedBlock: string | null;
    library: DesignerLibraryBlock[];
};

function templateBlockRoutes(template: string): DesignerBlockRoutes {
    return {
        add: (page) => storeBlock.url({ template, page }),
        state: (block) => blockState.url({ template, block }),
        move: (block) => moveBlock.url({ template, block }),
        duplicate: (block) => duplicateBlock.url({ template, block }),
        upgrade: (block) => upgradeBlock.url({ template, block }),
        visibility: (block) => blockVisibility.url({ template, block }),
        destroy: (block) => destroyBlock.url({ template, block }),
    };
}

function templatePageRoutes(template: string): DesignerPageRoutes {
    return {
        href: (page) => designer.url(template, { query: { page } }),
        store: storePage.form(template),
        update: (page) => updatePage.form({ template, page }),
        destroy: (page) => destroyPage.form({ template, page }),
    };
}

export default function TemplateDesigner({
    template,
    design,
    page,
    pages,
    blocks,
    selectedBlock,
    library,
}: TemplateDesignerProps) {
    const renderContext = useMemo(() => {
        const pageIds = new Set(pages.map((item) => item.public_id));

        return {
            assetUrl: () => null,
            pageHref: (id: string) =>
                pageIds.has(id)
                    ? designer.url(template.public_id, { query: { page: id } })
                    : null,
            vehicle: () => null,
            vehicles: [],
            hasPopup: () => false,
            openPopup: null,
        };
    }, [pages, template.public_id]);
    const designerContext = useMemo(
        () => ({
            siteId: '',
            assets: [],
            canUpload: false,
            pages,
            blocks,
            vehicles: [],
            popups: [],
        }),
        [pages, blocks],
    );
    const [selectedId, setSelectedId] = useState<string | null>(selectedBlock);
    const [serverSelection, setServerSelection] = useState(selectedBlock);

    if (selectedBlock !== serverSelection) {
        setServerSelection(selectedBlock);
        setSelectedId(selectedBlock);
    }

    const blockRoutes = templateBlockRoutes(template.public_id);
    const autosave = useBlockAutosave(blockRoutes.state);
    const { errors } = usePage<{ errors: Record<string, string> }>().props;
    const selected = blocks.find((block) => block.public_id === selectedId);
    const stateOf = (block: DesignerBlock) =>
        autosave.drafts[block.public_id] ?? block.state;
    const unsaved =
        autosave.status === 'pending' || autosave.status === 'saving';
    const visit = { preserveScroll: true, preserveState: true } as const;

    return (
        <>
            <Head title={`Шаблон — ${template.name}`} />
            <div className="flex min-h-svh flex-col bg-muted/40 lg:h-svh">
                <header className="flex min-w-0 items-center gap-3 border-b bg-background px-3 py-2 sm:px-4">
                    <Button asChild variant="ghost" size="icon">
                        <Link
                            href={show(template.public_id)}
                            aria-label="Назад к шаблону"
                        >
                            <ArrowLeft aria-hidden="true" />
                        </Link>
                    </Button>
                    <div className="min-w-0 flex-1">
                        <h1 className="truncate text-sm font-semibold sm:text-base">
                            {template.name}
                        </h1>
                        <p className="truncate text-xs text-muted-foreground">
                            {page.title}
                        </p>
                    </div>
                    <AutosaveIndicator status={autosave.status} />
                    <Badge variant="outline">Черновик шаблона</Badge>
                    {unsaved ? (
                        <Button variant="outline" size="sm" disabled>
                            <Eye aria-hidden="true" />
                            Предпросмотр
                        </Button>
                    ) : (
                        <Button asChild variant="outline" size="sm">
                            <a
                                href={preview.url(template.public_id, {
                                    query: { page: page.public_id },
                                })}
                                target="_blank"
                                rel="noopener"
                            >
                                <Eye aria-hidden="true" />
                                Предпросмотр
                            </a>
                        </Button>
                    )}
                    <Button asChild size="sm">
                        <Link
                            href={show(template.public_id)}
                            aria-label="Публикация шаблона"
                        >
                            <Settings aria-hidden="true" />
                            <span className="hidden sm:inline">Публикация</span>
                        </Link>
                    </Button>
                </header>

                <div className="flex min-h-0 flex-1 flex-col lg:flex-row">
                    <DesignerSidebar
                        blockRoutes={blockRoutes}
                        pageId={page.public_id}
                        blocks={blocks}
                        referenceIssues={{}}
                        library={library}
                        selectedId={selectedId}
                        onSelect={setSelectedId}
                        canEditStructure
                        pages={
                            <PagesPanel
                                routes={templatePageRoutes(template.public_id)}
                                pages={pages}
                                currentPageId={page.public_id}
                                canEdit
                                canAddPages
                            />
                        }
                    />

                    <main
                        aria-label="Холст"
                        className="min-w-0 flex-1 bg-neutral-100 lg:overflow-y-auto"
                    >
                        <div className="min-h-full bg-white">
                            <BlockRenderContext value={renderContext}>
                                <SiteTheme tokens={design}>
                                    {blocks.length === 0 ? (
                                        <div className="flex min-h-64 items-center justify-center p-6 text-center text-sm text-neutral-500">
                                            На странице пока нет блоков.
                                        </div>
                                    ) : (
                                        blocks.map((block, index) => (
                                            <CanvasBlock
                                                key={block.public_id}
                                                block={block}
                                                state={stateOf(block)}
                                                selected={
                                                    block.public_id ===
                                                    selectedId
                                                }
                                                onSelect={() =>
                                                    setSelectedId(
                                                        block.public_id,
                                                    )
                                                }
                                                canEditStructure
                                                actions={{
                                                    canMoveUp: index > 0,
                                                    canMoveDown:
                                                        index <
                                                        blocks.length - 1,
                                                    onMoveUp: () =>
                                                        router.post(
                                                            blockRoutes.move(
                                                                block.public_id,
                                                            ),
                                                            {
                                                                direction: 'up',
                                                            },
                                                            visit,
                                                        ),
                                                    onMoveDown: () =>
                                                        router.post(
                                                            blockRoutes.move(
                                                                block.public_id,
                                                            ),
                                                            {
                                                                direction:
                                                                    'down',
                                                            },
                                                            visit,
                                                        ),
                                                    onDuplicate: () =>
                                                        router.post(
                                                            blockRoutes.duplicate(
                                                                block.public_id,
                                                            ),
                                                            {},
                                                            visit,
                                                        ),
                                                    onToggleHidden: () =>
                                                        router.patch(
                                                            blockRoutes.visibility(
                                                                block.public_id,
                                                            ),
                                                            {
                                                                hidden: !block.is_hidden,
                                                            },
                                                            visit,
                                                        ),
                                                    onDelete: () =>
                                                        router.delete(
                                                            blockRoutes.destroy(
                                                                block.public_id,
                                                            ),
                                                            visit,
                                                        ),
                                                }}
                                            />
                                        ))
                                    )}
                                </SiteTheme>
                            </BlockRenderContext>
                        </div>
                    </main>

                    <aside
                        aria-labelledby="designer-properties-heading"
                        className="border-t bg-background p-4 lg:w-80 lg:shrink-0 lg:overflow-y-auto lg:border-t-0 lg:border-l"
                    >
                        <h2
                            id="designer-properties-heading"
                            className="text-sm font-semibold"
                        >
                            Свойства
                        </h2>
                        {selected ? (
                            <div className="mt-3 flex flex-col gap-4">
                                <p className="text-sm">
                                    <span className="font-medium">
                                        {selected.name}
                                    </span>
                                    <span className="text-muted-foreground">
                                        {` · версия ${selected.version}`}
                                    </span>
                                </p>
                                <DesignerContext value={designerContext}>
                                    <PropertiesPanel
                                        key={selected.public_id}
                                        block={selected}
                                        state={stateOf(selected)}
                                        errors={errors}
                                        disabled={false}
                                        onChange={(state) =>
                                            autosave.update(
                                                selected.public_id,
                                                state,
                                            )
                                        }
                                    />
                                </DesignerContext>
                                <p className="text-xs text-muted-foreground">
                                    Изменения сохраняются в черновик шаблона
                                    автоматически. Изображения, автомобили и
                                    попапы клиент выберет на своём сайте.
                                </p>
                            </div>
                        ) : (
                            <p className="mt-2 text-sm text-muted-foreground">
                                Выберите блок, чтобы увидеть его свойства.
                            </p>
                        )}
                    </aside>
                </div>
            </div>
        </>
    );
}
