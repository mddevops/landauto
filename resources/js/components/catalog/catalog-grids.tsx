import { Link } from '@inertiajs/react';
import { Car, Check, ImageOff, Images, Palette, Search } from 'lucide-react';
import { useMemo, useState } from 'react';
import type { ReactNode } from 'react';
import { Badge } from '@/components/ui/badge';
import { Input } from '@/components/ui/input';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import { cn } from '@/lib/utils';
import type {
    CatalogGeneration,
    CatalogMark,
    CatalogModel,
    CatalogSeriesItem,
} from '@/types/catalog';

type Target<T> = {
    href?: (item: T) => string;
    onSelect?: (item: T) => void;
    selected?: (item: T) => boolean;
    disabled?: (item: T) => string | null;
};

function Tile<T>({
    item,
    target,
    className,
    children,
    label,
}: {
    item: T;
    target: Target<T>;
    className?: string;
    children: ReactNode;
    label: string;
}) {
    const selected = target.selected?.(item) ?? false;
    const disabledReason = target.disabled?.(item) ?? null;
    const classes = cn(
        'group relative flex min-w-0 flex-col rounded-xl border bg-card text-left shadow-xs transition-colors outline-none hover:border-primary/40 hover:bg-accent/40 focus-visible:ring-[3px] focus-visible:ring-ring/50',
        selected && 'border-primary ring-1 ring-primary',
        disabledReason !== null && 'pointer-events-none opacity-50',
        className,
    );
    const check = selected ? (
        <span className="absolute top-2 right-2 flex size-5 items-center justify-center rounded-full bg-primary text-primary-foreground">
            <Check className="size-3" aria-hidden="true" />
        </span>
    ) : null;

    if (target.href) {
        return (
            <Link
                href={target.href(item)}
                className={classes}
                aria-label={label}
            >
                {children}
                {check}
            </Link>
        );
    }

    return (
        <button
            type="button"
            className={classes}
            aria-label={disabledReason ?? label}
            aria-pressed={target.onSelect ? selected : undefined}
            disabled={disabledReason !== null}
            onClick={() => target.onSelect?.(item)}
        >
            {children}
            {check}
        </button>
    );
}

function CatalogImage({
    src,
    alt,
    className,
    fallback = 'car',
}: {
    src: string | null;
    alt: string;
    className?: string;
    fallback?: 'car' | 'logo';
}) {
    const [failed, setFailed] = useState(false);

    if (!src || failed) {
        return (
            <div
                className={cn(
                    'flex items-center justify-center rounded-lg bg-muted text-muted-foreground',
                    className,
                )}
                aria-hidden="true"
            >
                {fallback === 'logo' ? (
                    <Car className="size-6" />
                ) : (
                    <ImageOff className="size-6" />
                )}
            </div>
        );
    }

    return (
        <img
            src={src}
            alt={alt}
            loading="lazy"
            decoding="async"
            onError={() => setFailed(true)}
            className={className}
        />
    );
}

function normalize(value: string): string {
    return value.toLocaleLowerCase('ru').replace(/ё/g, 'е').trim();
}

export function MarkGrid({
    marks,
    ...target
}: { marks: CatalogMark[] } & Target<CatalogMark>) {
    const [query, setQuery] = useState('');
    const [scope, setScope] = useState<'popular' | 'all'>(
        marks.some((mark) => mark.popular) ? 'popular' : 'all',
    );
    const visible = useMemo(() => {
        const needle = normalize(query);

        if (needle !== '') {
            return marks.filter((mark) =>
                [mark.name, mark.name_ru ?? '', mark.url].some((value) =>
                    normalize(value).includes(needle),
                ),
            );
        }

        return scope === 'popular'
            ? marks.filter((mark) => mark.popular)
            : marks;
    }, [marks, query, scope]);

    return (
        <div className="flex flex-col gap-4">
            <div className="flex flex-col gap-3 sm:flex-row sm:items-center">
                <div className="relative flex-1">
                    <Search
                        className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                        aria-hidden="true"
                    />
                    <Input
                        type="search"
                        value={query}
                        onChange={(event) => setQuery(event.target.value)}
                        placeholder="Найти марку: Kia, Лада, Haval…"
                        aria-label="Поиск марки"
                        className="pl-9"
                    />
                </div>
                {query === '' && marks.some((mark) => mark.popular) && (
                    <ToggleGroup
                        type="single"
                        variant="outline"
                        value={scope}
                        onValueChange={(value) =>
                            value && setScope(value as 'popular' | 'all')
                        }
                        aria-label="Какие марки показать"
                    >
                        <ToggleGroupItem value="popular" className="px-3">
                            Популярные
                        </ToggleGroupItem>
                        <ToggleGroupItem value="all" className="px-3">
                            Все · {marks.length}
                        </ToggleGroupItem>
                    </ToggleGroup>
                )}
            </div>

            {visible.length === 0 ? (
                <p className="rounded-xl border border-dashed p-8 text-center text-sm text-muted-foreground">
                    Марка не найдена. Проверьте написание или выберите «Все».
                </p>
            ) : (
                <div className="grid grid-cols-2 gap-2 sm:grid-cols-3 md:grid-cols-4 xl:grid-cols-6">
                    {visible.map((mark) => (
                        <Tile
                            key={mark.url}
                            item={mark}
                            target={target}
                            label={mark.name}
                            className="items-center gap-2 p-3"
                        >
                            <CatalogImage
                                src={mark.logo}
                                alt=""
                                fallback="logo"
                                className="size-12 object-contain dark:brightness-110"
                            />
                            <span className="w-full truncate text-center text-sm font-medium">
                                {mark.name}
                            </span>
                        </Tile>
                    ))}
                </div>
            )}
        </div>
    );
}

export function ModelGrid({
    models,
    ...target
}: { models: CatalogModel[] } & Target<CatalogModel>) {
    const [query, setQuery] = useState('');
    const visible = useMemo(() => {
        const needle = normalize(query);

        return needle === ''
            ? models
            : models.filter((model) => normalize(model.name).includes(needle));
    }, [models, query]);

    return (
        <div className="flex flex-col gap-4">
            {models.length > 12 && (
                <div className="relative">
                    <Search
                        className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                        aria-hidden="true"
                    />
                    <Input
                        type="search"
                        value={query}
                        onChange={(event) => setQuery(event.target.value)}
                        placeholder="Найти модель"
                        aria-label="Поиск модели"
                        className="pl-9"
                    />
                </div>
            )}
            {visible.length === 0 ? (
                <p className="rounded-xl border border-dashed p-8 text-center text-sm text-muted-foreground">
                    {models.length === 0
                        ? 'У этой марки пока нет моделей в каталоге.'
                        : 'Модель не найдена.'}
                </p>
            ) : (
                <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
                    {visible.map((model) => (
                        <Tile
                            key={model.url}
                            item={model}
                            target={target}
                            label={model.name}
                            className="overflow-hidden"
                        >
                            <CatalogImage
                                src={model.image}
                                alt=""
                                className="aspect-[16/10] w-full rounded-t-xl rounded-b-none bg-muted object-cover"
                            />
                            <span className="flex flex-col gap-0.5 p-3">
                                <span className="truncate font-medium">
                                    {model.name}
                                </span>
                                {model.years && (
                                    <span className="text-xs text-muted-foreground">
                                        {model.years}
                                    </span>
                                )}
                            </span>
                        </Tile>
                    ))}
                </div>
            )}
        </div>
    );
}

export function GenerationList({
    generations,
    showMedia = false,
    ...target
}: {
    generations: CatalogGeneration[];
    showMedia?: boolean;
} & Target<CatalogSeriesItem>) {
    if (generations.length === 0) {
        return (
            <p className="rounded-xl border border-dashed p-8 text-center text-sm text-muted-foreground">
                У этой модели пока нет поколений в каталоге.
            </p>
        );
    }

    return (
        <div className="flex flex-col gap-6">
            {generations.map((generation) => (
                <section
                    key={generation.key}
                    aria-labelledby={`generation-${generation.key}`}
                    className="flex flex-col gap-3"
                >
                    <div className="flex items-baseline gap-2">
                        <h3
                            id={`generation-${generation.key}`}
                            className="font-semibold"
                        >
                            {generation.name}
                        </h3>
                        {generation.years && (
                            <span className="text-sm text-muted-foreground">
                                {generation.years}
                            </span>
                        )}
                    </div>
                    <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
                        {generation.series.map((series) => (
                            <Tile
                                key={series.ref}
                                item={series}
                                target={target}
                                label={`${generation.name}, ${series.name}`}
                                className="overflow-hidden"
                            >
                                <CatalogImage
                                    src={series.image}
                                    alt=""
                                    className="aspect-[16/10] w-full rounded-t-xl rounded-b-none bg-muted object-cover"
                                />
                                <span className="flex flex-col gap-1.5 p-3">
                                    <span className="truncate font-medium">
                                        {series.name}
                                    </span>
                                    {showMedia && (
                                        <span className="flex flex-wrap gap-1">
                                            <Badge
                                                variant={
                                                    series.colors_count
                                                        ? 'secondary'
                                                        : 'outline'
                                                }
                                                className="gap-1"
                                            >
                                                <Palette aria-hidden="true" />
                                                {series.colors_count
                                                    ? `Цветов: ${series.colors_count}`
                                                    : 'Нет цветов'}
                                            </Badge>
                                            {(series.gallery_count ?? 0) >
                                                0 && (
                                                <Badge
                                                    variant="secondary"
                                                    className="gap-1"
                                                >
                                                    <Images aria-hidden="true" />
                                                    {series.gallery_count}
                                                </Badge>
                                            )}
                                        </span>
                                    )}
                                </span>
                            </Tile>
                        ))}
                    </div>
                </section>
            ))}
        </div>
    );
}

export { CatalogImage };
