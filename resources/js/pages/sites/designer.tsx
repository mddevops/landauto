import { Head, Link } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import type { ReactNode } from 'react';
import { useState } from 'react';
import { blockRenderer } from '@/blocks/registry';
import { PagesPanel } from '@/components/designer/pages-panel';
import type {
    DesignerBlock,
    DesignerPage,
    DesignerSite,
} from '@/components/designer/types';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import { dashboard } from '@/routes';

type DesignerProps = {
    site: DesignerSite;
    page: { public_id: string; title: string };
    pages: DesignerPage[];
    blocks: DesignerBlock[];
    can: { editDesign: boolean };
};

type LeftTab = 'pages' | 'blocks';

export default function Designer({
    site,
    page,
    pages,
    blocks,
    can,
}: DesignerProps) {
    const [selectedId, setSelectedId] = useState<string | null>(null);
    const [leftTab, setLeftTab] = useState<LeftTab>('blocks');
    const selected = blocks.find((block) => block.public_id === selectedId);

    return (
        <>
            <Head title={`Дизайнер — ${site.name}`} />
            <div className="flex min-h-svh flex-col bg-muted/40 lg:h-svh">
                <header className="flex min-w-0 items-center gap-3 border-b bg-background px-3 py-2 sm:px-4">
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
                    <Badge variant="outline">Черновик</Badge>
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
                                    site={site}
                                    pages={pages}
                                    currentPageId={page.public_id}
                                    canEdit={can.editDesign}
                                />
                            ) : blocks.length === 0 ? (
                                <p className="text-sm text-muted-foreground">
                                    Блоков пока нет.
                                </p>
                            ) : (
                                <ul className="flex flex-col gap-1">
                                    {blocks.map((block) => (
                                        <li key={block.public_id}>
                                            <button
                                                type="button"
                                                aria-pressed={
                                                    block.public_id ===
                                                    selectedId
                                                }
                                                onClick={() =>
                                                    setSelectedId(
                                                        block.public_id,
                                                    )
                                                }
                                                className={cn(
                                                    'w-full rounded-md px-2 py-1.5 text-left text-sm hover:bg-muted focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none',
                                                    block.public_id ===
                                                        selectedId &&
                                                        'bg-muted font-medium',
                                                )}
                                            >
                                                {block.name}
                                            </button>
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </div>
                    </aside>

                    <main
                        aria-label="Холст"
                        className="min-w-0 flex-1 p-3 sm:p-6 lg:overflow-y-auto"
                    >
                        <div className="mx-auto min-h-full max-w-6xl overflow-hidden rounded-lg border bg-white shadow-sm">
                            {blocks.length === 0 ? (
                                <div className="flex min-h-64 items-center justify-center p-6 text-center text-sm text-neutral-500">
                                    На странице пока нет блоков.
                                </div>
                            ) : (
                                blocks.map((block) => (
                                    <CanvasBlock
                                        key={block.public_id}
                                        block={block}
                                        selected={
                                            block.public_id === selectedId
                                        }
                                        onSelect={() =>
                                            setSelectedId(block.public_id)
                                        }
                                    />
                                ))
                            )}
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
                            <dl className="mt-3 flex flex-col gap-3 text-sm">
                                <div>
                                    <dt className="text-muted-foreground">
                                        Блок
                                    </dt>
                                    <dd className="font-medium">
                                        {selected.name}
                                    </dd>
                                </div>
                                <div>
                                    <dt className="text-muted-foreground">
                                        Версия
                                    </dt>
                                    <dd>{selected.version}</dd>
                                </div>
                            </dl>
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

function Tabs<T extends string>({
    value,
    onChange,
    tabs,
}: {
    value: T;
    onChange: (value: T) => void;
    tabs: { value: T; label: ReactNode }[];
}) {
    return (
        <div role="tablist" className="flex border-b">
            {tabs.map((tab) => (
                <button
                    key={tab.value}
                    type="button"
                    role="tab"
                    id={`designer-tab-${tab.value}`}
                    aria-selected={tab.value === value}
                    aria-controls={`designer-panel-${tab.value}`}
                    onClick={() => onChange(tab.value)}
                    className={cn(
                        'flex-1 border-b-2 border-transparent px-3 py-2.5 text-sm text-muted-foreground hover:text-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none focus-visible:ring-inset',
                        tab.value === value &&
                            'border-primary font-medium text-foreground',
                    )}
                >
                    {tab.label}
                </button>
            ))}
        </div>
    );
}

function CanvasBlock({
    block,
    selected,
    onSelect,
}: {
    block: DesignerBlock;
    selected: boolean;
    onSelect: () => void;
}) {
    const Renderer = blockRenderer(block.slug);

    return (
        <div className="relative">
            <div inert>
                {Renderer ? (
                    <Renderer state={block.state} />
                ) : (
                    <div className="p-6 text-sm text-neutral-500">
                        {`Блок «${block.name}» не удаётся отобразить.`}
                    </div>
                )}
            </div>
            <button
                type="button"
                aria-label={`Выбрать блок «${block.name}»`}
                aria-pressed={selected}
                onClick={onSelect}
                className={cn(
                    'absolute inset-0 outline-none hover:ring-2 hover:ring-primary/40 hover:ring-inset focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-inset',
                    selected && 'ring-2 ring-primary ring-inset',
                )}
            />
        </div>
    );
}
