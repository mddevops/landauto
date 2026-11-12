import { Link } from '@inertiajs/react';
import { Images, ListChecks } from 'lucide-react';
import { CatalogImage } from '@/components/catalog/catalog-grids';
import { Swatch } from '@/components/catalog/photo-parts';
import { Badge } from '@/components/ui/badge';
import { Checkbox } from '@/components/ui/checkbox';
import { cn } from '@/lib/utils';
import type { VehicleCard } from '@/types/catalog';

export type VehicleGroup = {
    key: string;
    name: string;
    logo: string | null;
    models: { key: string; name: string; vehicles: VehicleCard[] }[];
    count: number;
};

const OTHER = 'Недоступны в каталоге';

export function normalizeSearch(value: string): string {
    return value.toLocaleLowerCase('ru').replace(/ё/g, 'е').trim();
}

export function matchesSearch(vehicle: VehicleCard, needle: string): boolean {
    if (needle === '') {
        return true;
    }

    return [
        vehicle.title,
        vehicle.mark?.name ?? '',
        vehicle.model?.name ?? '',
        vehicle.generation ?? '',
        vehicle.series ?? '',
    ].some((value) => normalizeSearch(value).includes(needle));
}

/**
 * Groups vehicles by mark, then model, keeping the server order inside each model.
 */
export function groupVehicles(vehicles: VehicleCard[]): VehicleGroup[] {
    const marks = new Map<string, VehicleGroup>();

    for (const vehicle of vehicles) {
        const markKey = vehicle.mark?.url ?? '~';
        const group = marks.get(markKey) ?? {
            key: markKey,
            name: vehicle.mark?.name ?? OTHER,
            logo: vehicle.mark?.logo ?? null,
            models: [],
            count: 0,
        };
        const modelKey = vehicle.model?.url ?? '~';
        let model = group.models.find((item) => item.key === modelKey);

        if (!model) {
            model = {
                key: modelKey,
                name: vehicle.model?.name ?? '',
                vehicles: [],
            };
            group.models.push(model);
        }

        model.vehicles.push(vehicle);
        group.count++;
        marks.set(markKey, group);
    }

    return [...marks.values()]
        .sort((a, b) =>
            a.key === '~'
                ? 1
                : b.key === '~'
                  ? -1
                  : a.name.localeCompare(b.name, 'ru'),
        )
        .map((group) => ({
            ...group,
            models: group.models.sort((a, b) =>
                a.name.localeCompare(b.name, 'ru'),
            ),
        }));
}

export function VehicleCardItem({
    vehicle,
    href,
    selected,
    onSelectedChange,
}: {
    vehicle: VehicleCard;
    href: string;
    selected?: boolean;
    onSelectedChange?: (value: boolean) => void;
}) {
    return (
        <article
            className={cn(
                'group relative flex min-w-0 flex-col overflow-hidden rounded-xl border bg-card shadow-xs transition-colors hover:border-primary/40',
                selected && 'border-primary ring-1 ring-primary',
                !vehicle.status && 'opacity-70',
            )}
        >
            {onSelectedChange && (
                <Checkbox
                    className="absolute top-2 left-2 z-10 bg-background shadow-sm"
                    checked={selected}
                    onCheckedChange={(value) =>
                        onSelectedChange(value === true)
                    }
                    aria-label={`Выбрать ${vehicle.title}`}
                />
            )}
            <Link
                href={href}
                className="flex flex-1 flex-col outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50"
            >
                <div className="relative">
                    <CatalogImage
                        src={vehicle.cover}
                        alt=""
                        className="aspect-[16/10] w-full bg-muted object-contain"
                    />
                    <div className="absolute top-2 right-2 flex gap-1">
                        {!vehicle.status && (
                            <Badge variant="secondary">В архиве</Badge>
                        )}
                        {!vehicle.catalog_available && (
                            <Badge variant="destructive">Нет в каталоге</Badge>
                        )}
                    </div>
                </div>
                <div className="flex flex-1 flex-col gap-2 p-3">
                    <div className="min-w-0">
                        <h3 className="truncate font-medium">
                            {vehicle.title}
                        </h3>
                        <p className="truncate text-xs text-muted-foreground">
                            {[vehicle.generation, vehicle.series, vehicle.years]
                                .filter(Boolean)
                                .join(' · ')}
                        </p>
                    </div>
                    <div className="mt-auto flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-muted-foreground">
                        {vehicle.swatches.length > 0 && (
                            <span
                                className="flex items-center -space-x-1"
                                title={vehicle.swatches
                                    .map((s) => s.name)
                                    .join(', ')}
                            >
                                {vehicle.swatches.slice(0, 6).map((swatch) => (
                                    <Swatch
                                        key={swatch.name}
                                        hex={swatch.hex}
                                        className="size-3.5 ring-2 ring-card"
                                    />
                                ))}
                                {vehicle.swatches.length > 6 && (
                                    <span className="pl-2">
                                        +{vehicle.swatches.length - 6}
                                    </span>
                                )}
                            </span>
                        )}
                        <span className="flex items-center gap-1">
                            <Images className="size-3.5" aria-hidden="true" />
                            {vehicle.photos_count}
                        </span>
                        <span className="flex items-center gap-1">
                            <ListChecks
                                className="size-3.5"
                                aria-hidden="true"
                            />
                            {vehicle.offers_count}
                        </span>
                        <span className="ml-auto font-medium text-foreground">
                            {vehicle.price_from ??
                                (vehicle.offers_count > 0
                                    ? 'Цена по запросу'
                                    : '')}
                        </span>
                    </div>
                </div>
            </Link>
        </article>
    );
}

export function VehicleGroups({
    groups,
    href,
    selected,
    onToggle,
}: {
    groups: VehicleGroup[];
    href: (vehicle: VehicleCard) => string;
    selected?: Set<string>;
    onToggle?: (ids: string[], value: boolean) => void;
}) {
    return (
        <div className="flex flex-col gap-8">
            {groups.map((group) => {
                const ids = group.models.flatMap((model) =>
                    model.vehicles.map((vehicle) => vehicle.public_id),
                );
                const all =
                    selected !== undefined &&
                    ids.every((id) => selected.has(id));

                return (
                    <section
                        key={group.key}
                        aria-labelledby={`mark-${group.key}`}
                        className="flex flex-col gap-4"
                    >
                        <div className="flex items-center gap-3 border-b pb-2">
                            {onToggle && (
                                <Checkbox
                                    checked={all}
                                    onCheckedChange={(value) =>
                                        onToggle(ids, value === true)
                                    }
                                    aria-label={`Выбрать все ${group.name}`}
                                />
                            )}
                            {group.logo && (
                                <CatalogImage
                                    src={group.logo}
                                    alt=""
                                    fallback="logo"
                                    className="size-7 object-contain"
                                />
                            )}
                            <h2
                                id={`mark-${group.key}`}
                                className="text-lg font-semibold"
                            >
                                {group.name}
                            </h2>
                            <Badge variant="secondary">{group.count}</Badge>
                        </div>
                        {group.models.map((model) => (
                            <div
                                key={model.key}
                                className="flex flex-col gap-2"
                            >
                                {model.name && (
                                    <h3 className="text-sm font-medium text-muted-foreground">
                                        {model.name} · {model.vehicles.length}
                                    </h3>
                                )}
                                <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3 2xl:grid-cols-4">
                                    {model.vehicles.map((vehicle) => (
                                        <VehicleCardItem
                                            key={vehicle.public_id}
                                            vehicle={vehicle}
                                            href={href(vehicle)}
                                            selected={selected?.has(
                                                vehicle.public_id,
                                            )}
                                            onSelectedChange={
                                                onToggle
                                                    ? (value) =>
                                                          onToggle(
                                                              [
                                                                  vehicle.public_id,
                                                              ],
                                                              value,
                                                          )
                                                    : undefined
                                            }
                                        />
                                    ))}
                                </div>
                            </div>
                        ))}
                    </section>
                );
            })}
        </div>
    );
}
