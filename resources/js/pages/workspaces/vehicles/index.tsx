import { Head, Link } from '@inertiajs/react';
import { Car, Plus, Search, TriangleAlert } from 'lucide-react';
import { useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardDescription, CardHeader } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { dashboard } from '@/routes';
import { create, index, show } from '@/routes/workspace/vehicles';
import type { VehicleCatalogTitle } from '@/types/catalog';

type LibraryVehicle = {
    public_id: string;
    custom_name: string | null;
    status: boolean;
    media_sets_count: number;
    catalog: VehicleCatalogTitle | null;
    catalog_available: boolean;
};

function vehicleTitle(vehicle: LibraryVehicle): string {
    return vehicle.custom_name ?? vehicle.catalog?.title ?? 'Модель недоступна';
}

export default function WorkspaceVehicles({
    vehicles,
}: {
    vehicles: LibraryVehicle[];
}) {
    const [query, setQuery] = useState('');
    const needle = query.trim().toLocaleLowerCase('ru');
    const shown = needle
        ? vehicles.filter((vehicle) =>
              [
                  vehicleTitle(vehicle),
                  vehicle.catalog?.title ?? '',
                  vehicle.catalog?.generation ?? '',
                  vehicle.catalog?.series ?? '',
              ]
                  .join(' ')
                  .toLocaleLowerCase('ru')
                  .includes(needle),
          )
        : vehicles;

    return (
        <>
            <Head title="Библиотека автомобилей" />
            <main className="flex min-w-0 flex-1 flex-col gap-6 p-4 sm:p-6">
                <header className="flex min-w-0 flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                    <div className="min-w-0 space-y-1">
                        <p className="text-sm text-muted-foreground">
                            Рабочее пространство
                        </p>
                        <h1 className="text-2xl font-semibold tracking-tight sm:text-3xl">
                            Библиотека автомобилей
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Подготовленные модели для повторного использования.
                            На сайт добавляется независимая копия без цен.
                        </p>
                    </div>
                    <Button asChild>
                        <Link href={create()}>
                            <Plus aria-hidden="true" />
                            Добавить из каталога
                        </Link>
                    </Button>
                </header>

                {vehicles.length === 0 ? (
                    <Card className="border-dashed">
                        <CardHeader>
                            <h2 className="leading-none font-semibold">
                                В библиотеке пока нет автомобилей
                            </h2>
                            <CardDescription>
                                Добавьте модель из каталога платформы или
                                сохраните автомобиль со страницы сайта.
                            </CardDescription>
                        </CardHeader>
                    </Card>
                ) : (
                    <>
                        <div className="relative max-w-sm">
                            <Search
                                aria-hidden="true"
                                className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                            />
                            <Input
                                type="search"
                                value={query}
                                onChange={(event) =>
                                    setQuery(event.target.value)
                                }
                                placeholder="Поиск по названию"
                                aria-label="Поиск по библиотеке"
                                className="pl-9"
                            />
                        </div>
                        {shown.length === 0 ? (
                            <p className="text-sm text-muted-foreground">
                                Ничего не найдено.
                            </p>
                        ) : (
                            <ul className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                                {shown.map((vehicle) => (
                                    <li key={vehicle.public_id}>
                                        <Link
                                            href={show(vehicle.public_id)}
                                            className="flex h-full min-w-0 flex-col gap-3 rounded-xl border bg-card p-4 shadow-sm transition-colors hover:bg-accent focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none"
                                        >
                                            <div className="flex items-start gap-3">
                                                <Car
                                                    aria-hidden="true"
                                                    className="mt-0.5 size-5 shrink-0 text-muted-foreground"
                                                />
                                                <div className="min-w-0 flex-1">
                                                    <h2 className="font-semibold break-words">
                                                        {vehicleTitle(vehicle)}
                                                    </h2>
                                                    {vehicle.catalog && (
                                                        <p className="text-sm text-muted-foreground">
                                                            {`${vehicle.catalog.generation} · ${vehicle.catalog.series}`}
                                                        </p>
                                                    )}
                                                </div>
                                                {!vehicle.status && (
                                                    <Badge variant="secondary">
                                                        В архиве
                                                    </Badge>
                                                )}
                                            </div>
                                            <p className="text-sm text-muted-foreground">
                                                {`Вариантов изображений: ${vehicle.media_sets_count}`}
                                            </p>
                                            {!vehicle.catalog_available && (
                                                <p className="flex items-center gap-2 text-sm text-amber-700 dark:text-amber-400">
                                                    <TriangleAlert
                                                        aria-hidden="true"
                                                        className="size-4 shrink-0"
                                                    />
                                                    Серия выключена в каталоге
                                                </p>
                                            )}
                                        </Link>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </>
                )}
            </main>
        </>
    );
}

WorkspaceVehicles.layout = {
    breadcrumbs: [
        { title: 'Все сайты', href: dashboard() },
        { title: 'Библиотека автомобилей', href: index() },
    ],
};
