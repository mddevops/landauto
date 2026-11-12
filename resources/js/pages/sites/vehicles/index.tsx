import { Head, Link } from '@inertiajs/react';
import { Car, Copy, Plus, Search } from 'lucide-react';
import { useMemo, useState } from 'react';
import {
    groupVehicles,
    matchesSearch,
    normalizeSearch,
    VehicleGroups,
} from '@/components/catalog/vehicle-cards';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { dashboard } from '@/routes';
import { create, show } from '@/routes/sites/vehicles';
import { create as importVehicles } from '@/routes/sites/vehicles/imports';
import type { VehicleCard } from '@/types/catalog';

type VehiclesIndexProps = {
    site: { public_id: string; name: string };
    vehicles: VehicleCard[];
    can: { editVehicles: boolean; importVehicles: boolean };
};

export default function VehiclesIndex({
    site,
    vehicles,
    can,
}: VehiclesIndexProps) {
    const [query, setQuery] = useState('');
    const groups = useMemo(() => {
        const needle = normalizeSearch(query);

        return groupVehicles(
            vehicles.filter((vehicle) => matchesSearch(vehicle, needle)),
        );
    }, [vehicles, query]);

    return (
        <>
            <Head title={`Автомобили — ${site.name}`} />
            <main className="flex min-w-0 flex-1 flex-col gap-6 p-4 sm:p-6">
                <header className="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                    <div className="min-w-0 space-y-1">
                        <p className="truncate text-sm text-muted-foreground">
                            {site.name}
                        </p>
                        <h1 className="text-2xl font-semibold tracking-tight sm:text-3xl">
                            Автомобили
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Автомобили этого сайта с фото, комплектациями и
                            ценами.
                        </p>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        {can.importVehicles && (
                            <Button variant="outline" asChild>
                                <Link href={importVehicles(site.public_id)}>
                                    <Copy aria-hidden="true" />
                                    С другого сайта
                                </Link>
                            </Button>
                        )}
                        {can.editVehicles && (
                            <Button asChild>
                                <Link href={create(site.public_id)}>
                                    <Plus aria-hidden="true" />
                                    Добавить автомобиль
                                </Link>
                            </Button>
                        )}
                    </div>
                </header>

                {vehicles.length === 0 ? (
                    <div className="flex flex-col items-center gap-3 rounded-xl border border-dashed p-10 text-center">
                        <Car
                            className="size-8 text-muted-foreground"
                            aria-hidden="true"
                        />
                        <p className="font-medium">Автомобилей пока нет</p>
                        <p className="max-w-md text-sm text-muted-foreground">
                            Добавьте автомобиль из каталога auto.ru или
                            скопируйте готовый из своего Каталога.
                        </p>
                    </div>
                ) : (
                    <>
                        {vehicles.length > 6 && (
                            <div className="relative max-w-md">
                                <Search
                                    className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                                    aria-hidden="true"
                                />
                                <Input
                                    type="search"
                                    value={query}
                                    onChange={(event) =>
                                        setQuery(event.target.value)
                                    }
                                    placeholder="Найти: марка, модель, кузов"
                                    aria-label="Поиск автомобиля"
                                    className="pl-9"
                                />
                            </div>
                        )}
                        {groups.length === 0 ? (
                            <p className="rounded-xl border border-dashed p-8 text-center text-sm text-muted-foreground">
                                Ничего не найдено.
                            </p>
                        ) : (
                            <VehicleGroups
                                groups={groups}
                                href={(vehicle) =>
                                    show.url({
                                        site: site.public_id,
                                        vehicle: vehicle.public_id,
                                    })
                                }
                            />
                        )}
                    </>
                )}
            </main>
        </>
    );
}

VehiclesIndex.layout = {
    breadcrumbs: [{ title: 'Все сайты', href: dashboard() }],
};
