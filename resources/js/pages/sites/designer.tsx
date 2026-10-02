import { Head, Link } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import { useState } from 'react';
import { blockRenderer } from '@/blocks/registry';
import type { BlockState } from '@/blocks/state';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import { dashboard } from '@/routes';

type DesignerBlock = {
    public_id: string;
    slug: string;
    name: string;
    version: string;
    state: BlockState;
};

type DesignerProps = {
    site: {
        public_id: string;
        name: string;
    };
    page: {
        public_id: string;
        title: string;
    } | null;
    blocks: DesignerBlock[];
};

export default function Designer({ site, page, blocks }: DesignerProps) {
    const [selectedId, setSelectedId] = useState<string | null>(null);
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
                        {page && (
                            <p className="truncate text-xs text-muted-foreground">
                                {page.title}
                            </p>
                        )}
                    </div>
                    <Badge variant="outline">Черновик</Badge>
                </header>

                <div className="flex min-h-0 flex-1 flex-col lg:flex-row">
                    <aside
                        aria-labelledby="designer-page-heading"
                        className="border-b bg-background p-4 lg:w-64 lg:shrink-0 lg:overflow-y-auto lg:border-r lg:border-b-0"
                    >
                        <h2
                            id="designer-page-heading"
                            className="text-sm font-semibold"
                        >
                            Блоки страницы
                        </h2>
                        {blocks.length === 0 ? (
                            <p className="mt-2 text-sm text-muted-foreground">
                                Блоков пока нет.
                            </p>
                        ) : (
                            <ul className="mt-2 flex flex-col gap-1">
                                {blocks.map((block) => (
                                    <li key={block.public_id}>
                                        <button
                                            type="button"
                                            aria-pressed={
                                                block.public_id === selectedId
                                            }
                                            onClick={() =>
                                                setSelectedId(block.public_id)
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
                        className="border-t bg-background p-4 lg:w-72 lg:shrink-0 lg:overflow-y-auto lg:border-t-0 lg:border-l"
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
