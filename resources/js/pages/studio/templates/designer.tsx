import { Head, Link, usePage } from '@inertiajs/react';
import { ArrowLeft, Eye, Settings } from 'lucide-react';
import { useMemo, useState } from 'react';
import type { DesignTokens } from '@/blocks/design';
import { BlockRenderContext } from '@/blocks/render-context';
import { SiteTheme } from '@/blocks/theme';
import {
    AutosaveIndicator,
    CanvasBlock,
    DesignerTabs as Tabs,
} from '@/components/designer/canvas';
import { DesignerContext } from '@/components/designer/designer-context';
import { Navigator } from '@/components/designer/navigator';
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
        seo: null,
    };
}

type LeftTab = 'pages' | 'blocks';

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

    const [leftTab, setLeftTab] = useState<LeftTab>('blocks');
    const blockRoutes = templateBlockRoutes(template.public_id);
    const autosave = useBlockAutosave(blockRoutes.state);
    const { errors } = usePage<{ errors: Record<string, string> }>().props;
    const selected = blocks.find((block) => block.public_id === selectedId);
    const stateOf = (block: DesignerBlock) =>
        autosave.drafts[block.public_id] ?? block.state;
    const unsaved =
        autosave.status === 'pending' || autosave.status === 'saving';

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
                    <aside
                        aria-label="Левая панель"
                        className="border-b bg-background lg:w-72 lg:shrink-0 lg:overflow-y-auto lg:border-r lg:border-b-0"
                    >
                        <Tabs
                            value={leftTab}
                            onChange={setLeftTab}
                            tabs={[
                                { value: 'pages', label: 'Страницы' },
                                { value: 'blocks', label: 'Блоки' },
                            ]}
                        />
                        <div
                            role="tabpanel"
                            id={`designer-panel-${leftTab}`}
                            aria-labelledby={`designer-tab-${leftTab}`}
                            className="p-4"
                        >
                            {leftTab === 'pages' ? (
                                <PagesPanel
                                    routes={templatePageRoutes(
                                        template.public_id,
                                    )}
                                    pages={pages}
                                    currentPageId={page.public_id}
                                    canEdit
                                    canAddPages
                                    canEditSeo={false}
                                    canEditSeoIndexing={false}
                                />
                            ) : (
                                <Navigator
                                    routes={blockRoutes}
                                    pageId={page.public_id}
                                    blocks={blocks}
                                    referenceIssues={{}}
                                    library={library}
                                    selectedId={selectedId}
                                    onSelect={setSelectedId}
                                    canEdit
                                />
                            )}
                        </div>
                    </aside>

                    <main
                        aria-label="Холст"
                        className="min-w-0 flex-1 p-3 sm:p-6 lg:overflow-y-auto"
                    >
                        <div className="mx-auto min-h-full max-w-6xl overflow-hidden rounded-lg border bg-white shadow-sm">
                            <BlockRenderContext value={renderContext}>
                                <SiteTheme tokens={design}>
                                    {blocks.length === 0 ? (
                                        <div className="flex min-h-64 items-center justify-center p-6 text-center text-sm text-neutral-500">
                                            На странице пока нет блоков.
                                        </div>
                                    ) : (
                                        blocks.map((block) => (
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
