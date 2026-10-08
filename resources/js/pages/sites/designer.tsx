import { Head, Link, router, usePage } from '@inertiajs/react';
import {
    ArrowLeft,
    Eye,
    LayoutList,
    Rocket,
    TriangleAlert,
} from 'lucide-react';
import { useMemo, useState } from 'react';
import type { DesignTokens } from '@/blocks/design';
import type { PopupRuntime } from '@/blocks/popup';
import { BlockRenderContext } from '@/blocks/render-context';
import { SiteTheme } from '@/blocks/theme';
import { vehicleFullTitle } from '@/blocks/vehicles';
import type { VehicleBinding } from '@/blocks/vehicles';
import {
    AutosaveIndicator,
    CanvasBlock,
    DesignerTabs as Tabs,
} from '@/components/designer/canvas';
import { DesignerContext } from '@/components/designer/designer-context';
import { DesignPanel } from '@/components/designer/design-panel';
import { useBlockAutosave } from '@/components/designer/use-block-autosave';
import { Navigator } from '@/components/designer/navigator';
import { PagesPanel } from '@/components/designer/pages-panel';
import { PropertiesPanel } from '@/components/designer/properties-panel';
import type {
    DesignerAsset,
    DesignerBlock,
    DesignerBlockRoutes,
    DesignerLibraryBlock,
    DesignerPage,
    DesignerPageRoutes,
    DesignerSite,
    ReferenceIssue,
} from '@/components/designer/types';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { dashboard } from '@/routes';
import { designer, preview } from '@/routes/sites';
import {
    destroy as destroyBlock,
    duplicate as duplicateBlock,
    move as moveBlock,
    state as blockState,
    store as storeBlock,
    visibility as blockVisibility,
} from '@/routes/sites/blocks';
import {
    destroy as destroyPage,
    store as storePage,
    update as updatePage,
} from '@/routes/sites/pages';
import { update as updatePageSeo } from '@/routes/sites/pages/seo';
import { index as deliveriesIndex } from '@/routes/sites/deliveries';
import { update as updateDesign } from '@/routes/sites/design';
import { show as formSecurity } from '@/routes/sites/form-security';
import { index as formsIndex } from '@/routes/sites/forms';
import { index as siteIntegrations } from '@/routes/sites/integrations';
import { index as popupsIndex } from '@/routes/sites/popups';
import { show as publishing } from '@/routes/sites/publishing';
import { index as submissionsIndex } from '@/routes/sites/submissions';

type DesignerProps = {
    site: DesignerSite;
    design: DesignTokens;
    page: { public_id: string; title: string };
    pages: DesignerPage[];
    blocks: DesignerBlock[];
    assets: DesignerAsset[];
    vehicles: VehicleBinding[];
    popups: PopupRuntime[];
    referenceIssues: Record<string, ReferenceIssue[]>;
    selectedBlock: string | null;
    library: DesignerLibraryBlock[];
    can: {
        editDesign: boolean;
        editStructure: boolean;
        addPage: boolean;
        editContent: boolean;
        manageAssets: boolean;
        preview: boolean;
        viewSubmissions: boolean;
        viewIntegrations: boolean;
        viewDeliveryLogs: boolean;
        editSeo: boolean;
        editSeoIndexing: boolean;
    };
};

function siteBlockRoutes(site: string): DesignerBlockRoutes {
    return {
        add: (page) => storeBlock.url({ site, page }),
        state: (block) => blockState.url({ site, block }),
        move: (block) => moveBlock.url({ site, block }),
        duplicate: (block) => duplicateBlock.url({ site, block }),
        visibility: (block) => blockVisibility.url({ site, block }),
        destroy: (block) => destroyBlock.url({ site, block }),
    };
}

function sitePageRoutes(site: string): DesignerPageRoutes {
    return {
        href: (page) => designer.url(site, { query: { page } }),
        store: storePage.form(site),
        update: (page) => updatePage.form({ site, page }),
        destroy: (page) => destroyPage.form({ site, page }),
        seo: (page) => updatePageSeo({ site, page }),
    };
}

type LeftTab = 'pages' | 'blocks';
type RightTab = 'block' | 'design';

export default function Designer({
    site,
    design,
    page,
    pages,
    blocks,
    assets,
    vehicles,
    popups,
    referenceIssues,
    selectedBlock,
    library,
    can,
}: DesignerProps) {
    const renderContext = useMemo(() => {
        const urls = new Map(
            assets.map((asset) => [asset.public_id, asset.url]),
        );
        const pageIds = new Set(pages.map((sitePage) => sitePage.public_id));
        const vehicleMap = new Map(
            vehicles.map((vehicle) => [vehicle.public_id, vehicle]),
        );

        return {
            assetUrl: (id: string) => urls.get(id) ?? null,
            pageHref: (id: string) =>
                pageIds.has(id)
                    ? designer.url(site.public_id, { query: { page: id } })
                    : null,
            vehicle: (id: string) => vehicleMap.get(id) ?? null,
            vehicles,
            hasPopup: (id: string) =>
                popups.some((popup) => popup.public_id === id),
            openPopup: null,
        };
    }, [assets, pages, vehicles, popups, site.public_id]);
    const designerContext = useMemo(
        () => ({
            siteId: site.public_id,
            assets,
            canUpload: can.manageAssets,
            pages,
            blocks,
            vehicles: vehicles.map((vehicle) => ({
                public_id: vehicle.public_id,
                title: vehicleFullTitle(vehicle),
            })),
            popups: popups.map((popup) => ({
                public_id: popup.public_id,
                name: popup.name,
            })),
        }),
        [
            site.public_id,
            assets,
            can.manageAssets,
            pages,
            blocks,
            vehicles,
            popups,
        ],
    );
    const [selectedId, setSelectedId] = useState<string | null>(selectedBlock);
    const [serverSelection, setServerSelection] = useState(selectedBlock);

    if (selectedBlock !== serverSelection) {
        setServerSelection(selectedBlock);
        setSelectedId(selectedBlock);
    }

    const [leftTab, setLeftTab] = useState<LeftTab>('blocks');
    const [rightTab, setRightTab] = useState<RightTab>('block');
    const blockRoutes = siteBlockRoutes(site.public_id);
    const autosave = useBlockAutosave(blockRoutes.state);
    const [designDraft, setDesignDraft] = useState<DesignTokens | null>(null);
    const [saving, setSaving] = useState(false);
    const tokens = designDraft ?? design;
    const { errors } = usePage<{ errors: Record<string, string> }>().props;
    const selected = blocks.find((block) => block.public_id === selectedId);
    const selectedIssues = selected
        ? (referenceIssues[selected.public_id] ?? [])
        : [];
    const stateOf = (block: DesignerBlock) =>
        autosave.drafts[block.public_id] ?? block.state;

    const saveDesign = () => {
        const draft = designDraft;

        if (draft === null) {
            return;
        }

        router.patch(updateDesign.url(site.public_id), draft, {
            async: true,
            preserveScroll: true,
            preserveState: true,
            onStart: () => setSaving(true),
            onFinish: () => setSaving(false),
            onSuccess: () =>
                setDesignDraft((current) =>
                    current === draft ? null : current,
                ),
        });
    };

    return (
        <>
            <Head title={`Дизайнер — ${site.name}`} />
            <div className="flex min-h-svh flex-col bg-muted/40 lg:h-svh">
                <header className="flex min-w-0 items-center gap-2 border-b bg-background px-3 py-2 sm:gap-3 sm:px-4">
                    <Button asChild variant="ghost" size="icon">
                        <Link href={dashboard()} aria-label="Назад к сайтам">
                            <ArrowLeft aria-hidden="true" />
                        </Link>
                    </Button>
                    <div className="min-w-0 flex-1">
                        <h1 className="truncate text-sm font-semibold sm:text-base">
                            {site.name}
                        </h1>
                        <p className="truncate text-xs text-muted-foreground">
                            {page.title}
                        </p>
                    </div>
                    <AutosaveIndicator status={autosave.status} />
                    <Badge variant="outline">Черновик</Badge>
                    <SiteSectionsMenu
                        siteId={site.public_id}
                        canViewSubmissions={can.viewSubmissions}
                        canViewIntegrations={can.viewIntegrations}
                        canViewDeliveryLogs={can.viewDeliveryLogs}
                    />
                    {can.preview &&
                        (autosave.status === 'pending' ||
                        autosave.status === 'saving' ? (
                            <Button
                                variant="outline"
                                size="sm"
                                disabled
                                aria-label="Предпросмотр"
                            >
                                <Eye aria-hidden="true" />
                                <span className="hidden sm:inline">
                                    Предпросмотр
                                </span>
                            </Button>
                        ) : (
                            <Button asChild variant="outline" size="sm">
                                <a
                                    href={preview.url(site.public_id, {
                                        query: { page: page.public_id },
                                    })}
                                    target="_blank"
                                    rel="noopener"
                                    aria-label="Предпросмотр"
                                >
                                    <Eye aria-hidden="true" />
                                    <span className="hidden sm:inline">
                                        Предпросмотр
                                    </span>
                                </a>
                            </Button>
                        ))}
                    <Button asChild size="sm">
                        <Link
                            href={publishing(site.public_id)}
                            aria-label="Публикация"
                        >
                            <Rocket aria-hidden="true" />
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
                                    routes={sitePageRoutes(site.public_id)}
                                    pages={pages}
                                    currentPageId={page.public_id}
                                    canEdit={can.editDesign}
                                    canAddPages={can.addPage}
                                    canEditSeo={can.editSeo}
                                    canEditSeoIndexing={can.editSeoIndexing}
                                />
                            ) : (
                                <Navigator
                                    routes={blockRoutes}
                                    pageId={page.public_id}
                                    blocks={blocks}
                                    referenceIssues={referenceIssues}
                                    library={library}
                                    selectedId={selectedId}
                                    onSelect={setSelectedId}
                                    canEdit={can.editStructure}
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
                                <SiteTheme tokens={tokens}>
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
                        <div className="-mx-4 mt-2">
                            <Tabs
                                value={rightTab}
                                onChange={setRightTab}
                                tabs={[
                                    { value: 'block', label: 'Блок' },
                                    { value: 'design', label: 'Стиль сайта' },
                                ]}
                            />
                        </div>
                        <div
                            role="tabpanel"
                            id={`designer-panel-${rightTab}`}
                            aria-labelledby={`designer-tab-${rightTab}`}
                            className="pt-3"
                        >
                            {rightTab === 'design' ? (
                                <DesignPanel
                                    tokens={tokens}
                                    errors={errors}
                                    canEdit={can.editDesign}
                                    dirty={designDraft !== null}
                                    saving={saving}
                                    onChange={setDesignDraft}
                                    onSave={saveDesign}
                                />
                            ) : selected ? (
                                <div className="mt-3 flex flex-col gap-4">
                                    <p className="text-sm">
                                        <span className="font-medium">
                                            {selected.name}
                                        </span>
                                        <span className="text-muted-foreground">
                                            {` · версия ${selected.version}`}
                                        </span>
                                    </p>
                                    <ReferenceIssues issues={selectedIssues} />
                                    <DesignerContext value={designerContext}>
                                        <PropertiesPanel
                                            key={selected.public_id}
                                            block={selected}
                                            state={stateOf(selected)}
                                            errors={{
                                                ...Object.fromEntries(
                                                    selectedIssues.map(
                                                        (issue) => [
                                                            issue.path,
                                                            issue.message,
                                                        ],
                                                    ),
                                                ),
                                                ...errors,
                                            }}
                                            disabled={!can.editContent}
                                            onChange={(state) =>
                                                autosave.update(
                                                    selected.public_id,
                                                    state,
                                                )
                                            }
                                        />
                                    </DesignerContext>
                                    {can.editContent && (
                                        <p className="text-xs text-muted-foreground">
                                            Изменения сохраняются в черновик
                                            автоматически.
                                        </p>
                                    )}
                                </div>
                            ) : (
                                <p className="mt-2 text-sm text-muted-foreground">
                                    Выберите блок, чтобы увидеть его свойства.
                                </p>
                            )}
                        </div>
                    </aside>
                </div>
            </div>
        </>
    );
}

function ReferenceIssues({ issues }: { issues: ReferenceIssue[] }) {
    if (issues.length === 0) {
        return null;
    }

    const blocking = issues.some((issue) => issue.severity === 'error');

    return (
        <div
            role="status"
            aria-label="Устаревшие ссылки"
            className="flex gap-2 rounded-md border border-amber-300 bg-amber-50 p-3 text-sm text-amber-950"
        >
            <TriangleAlert
                aria-hidden="true"
                className="mt-0.5 size-4 shrink-0"
            />
            <div className="min-w-0 space-y-1">
                <p className="font-medium">
                    {blocking
                        ? 'Блок ссылается на удалённые или выключенные элементы. Исправьте ссылки перед публикацией.'
                        : 'Проверьте ссылки блока.'}
                </p>
                <ul className="list-disc space-y-0.5 pl-4">
                    {issues.map((issue) => (
                        <li key={`${issue.path}-${issue.kind}`}>
                            {issue.message}
                        </li>
                    ))}
                </ul>
            </div>
        </div>
    );
}

function SiteSectionsMenu({
    siteId,
    canViewSubmissions,
    canViewIntegrations,
    canViewDeliveryLogs,
}: {
    siteId: string;
    canViewSubmissions: boolean;
    canViewIntegrations: boolean;
    canViewDeliveryLogs: boolean;
}) {
    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button variant="outline" size="sm" aria-label="Разделы сайта">
                    <LayoutList aria-hidden="true" />
                    <span className="hidden sm:inline">Разделы</span>
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end">
                <DropdownMenuItem asChild>
                    <Link
                        className="block w-full cursor-pointer"
                        href={formsIndex(siteId)}
                    >
                        Формы
                    </Link>
                </DropdownMenuItem>
                <DropdownMenuItem asChild>
                    <Link
                        className="block w-full cursor-pointer"
                        href={popupsIndex(siteId)}
                    >
                        Попапы
                    </Link>
                </DropdownMenuItem>
                <DropdownMenuItem asChild>
                    <Link
                        className="block w-full cursor-pointer"
                        href={formSecurity(siteId)}
                    >
                        Защита форм
                    </Link>
                </DropdownMenuItem>
                {canViewSubmissions && (
                    <DropdownMenuItem asChild>
                        <Link
                            className="block w-full cursor-pointer"
                            href={submissionsIndex(siteId)}
                        >
                            Заявки
                        </Link>
                    </DropdownMenuItem>
                )}
                {canViewIntegrations && (
                    <DropdownMenuItem asChild>
                        <Link
                            className="block w-full cursor-pointer"
                            href={siteIntegrations(siteId)}
                        >
                            Интеграции сайта
                        </Link>
                    </DropdownMenuItem>
                )}
                {canViewDeliveryLogs && (
                    <DropdownMenuItem asChild>
                        <Link
                            className="block w-full cursor-pointer"
                            href={deliveriesIndex(siteId)}
                        >
                            Доставка заявок
                        </Link>
                    </DropdownMenuItem>
                )}
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
