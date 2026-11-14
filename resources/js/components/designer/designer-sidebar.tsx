import { Files, ListTree, PanelsTopLeft, Plus } from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { useState } from 'react';
import type { ReactNode } from 'react';
import {
    BlockLibrary,
    COMPONENT_CATEGORIES,
    Navigator,
} from '@/components/designer/navigator';
import type {
    DesignerBlock,
    DesignerBlockRoutes,
    DesignerLibraryBlock,
    ReferenceIssue,
} from '@/components/designer/types';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { cn } from '@/lib/utils';

type SidebarPanel = 'blocks' | 'pages' | 'navigator' | 'components';

const panels: { value: SidebarPanel; label: string; icon: LucideIcon }[] = [
    { value: 'blocks', label: 'Блоки', icon: Plus },
    { value: 'pages', label: 'Страницы', icon: Files },
    { value: 'navigator', label: 'Навигатор', icon: ListTree },
    { value: 'components', label: 'Компоненты', icon: PanelsTopLeft },
];

const blockCategories = { except: COMPONENT_CATEGORIES };
const componentCategories = { only: COMPONENT_CATEGORIES };

type DesignerSidebarProps = {
    blockRoutes: DesignerBlockRoutes;
    pageId: string;
    blocks: DesignerBlock[];
    referenceIssues: Record<string, ReferenceIssue[]>;
    library: DesignerLibraryBlock[];
    selectedId: string | null;
    onSelect: (id: string) => void;
    canEditStructure: boolean;
    pages: ReactNode;
};

/**
 * Left side of a Designer: a narrow icon rail switching between the Block library, Pages, the
 * Navigator of the current Page and Components (header and footer Blocks).
 */
export function DesignerSidebar({
    blockRoutes,
    pageId,
    blocks,
    referenceIssues,
    library,
    selectedId,
    onSelect,
    canEditStructure,
    pages,
}: DesignerSidebarProps) {
    const [panel, setPanel] = useState<SidebarPanel>('blocks');
    const current = panels.find((item) => item.value === panel) ?? panels[0];

    return (
        <aside
            aria-label="Левая панель"
            className="flex flex-col border-b bg-background lg:shrink-0 lg:flex-row lg:border-r lg:border-b-0"
        >
            <div
                role="tablist"
                aria-label="Разделы левой панели"
                aria-orientation="vertical"
                className="flex gap-1 border-b p-1.5 lg:flex-col lg:border-r lg:border-b-0"
            >
                {panels.map((item) => {
                    const Icon = item.icon;
                    const active = item.value === panel;

                    return (
                        <Tooltip key={item.value}>
                            <TooltipTrigger asChild>
                                <button
                                    type="button"
                                    role="tab"
                                    id={`designer-tab-${item.value}`}
                                    aria-selected={active}
                                    aria-controls={`designer-panel-${item.value}`}
                                    aria-label={item.label}
                                    onClick={() => setPanel(item.value)}
                                    className={cn(
                                        'flex size-9 items-center justify-center rounded-md text-muted-foreground transition-colors hover:bg-muted hover:text-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none',
                                        active && 'bg-muted text-foreground',
                                    )}
                                >
                                    <Icon
                                        aria-hidden="true"
                                        className="size-4"
                                    />
                                </button>
                            </TooltipTrigger>
                            <TooltipContent side="right">
                                {item.label}
                            </TooltipContent>
                        </Tooltip>
                    );
                })}
            </div>
            <div
                role="tabpanel"
                id={`designer-panel-${panel}`}
                aria-labelledby={`designer-tab-${panel}`}
                className="flex min-w-0 flex-col lg:w-64 lg:overflow-y-auto"
            >
                <h2 className="border-b px-4 py-3 text-sm font-semibold">
                    {current.label}
                </h2>
                <div className="p-4">
                    {panel === 'pages' ? (
                        pages
                    ) : panel === 'navigator' ? (
                        <Navigator
                            routes={blockRoutes}
                            blocks={blocks}
                            referenceIssues={referenceIssues}
                            selectedId={selectedId}
                            onSelect={onSelect}
                            canEdit={canEditStructure}
                        />
                    ) : panel === 'components' ? (
                        <div className="flex flex-col gap-3">
                            <p className="text-xs text-muted-foreground">
                                Шапка и подвал сайта. Добавленный компонент
                                появится внизу текущей страницы — поднимите его
                                в «Навигаторе».
                            </p>
                            <BlockLibrary
                                routes={blockRoutes}
                                pageId={pageId}
                                library={library}
                                categories={componentCategories}
                                canEdit={canEditStructure}
                                emptyText="Компонентов пока нет."
                            />
                        </div>
                    ) : (
                        <BlockLibrary
                            routes={blockRoutes}
                            pageId={pageId}
                            library={library}
                            categories={blockCategories}
                            canEdit={canEditStructure}
                            emptyText="Блоков пока нет."
                        />
                    )}
                </div>
            </div>
        </aside>
    );
}
