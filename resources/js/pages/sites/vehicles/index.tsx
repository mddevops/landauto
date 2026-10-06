import { Head, Link } from '@inertiajs/react';
import { Car, Plus, TriangleAlert } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardDescription, CardHeader } from '@/components/ui/card';
import { dashboard } from '@/routes';
import { create, show } from '@/routes/sites/vehicles';
import type { VehicleCatalogTitle } from '@/types/catalog';

type VehicleSummary = {
    public_id: string;
    status: boolean;
    sort_order: number;
    offers_count: number;
    media_sets_count: number;
    catalog: VehicleCatalogTitle | null;
    catalog_available: boolean;
};

type VehiclesIndexProps = {
    site: { public_id: string; name: string };
    vehicles: VehicleSummary[];
    can: { editVehicles: boolean };
};

export default function VehiclesIndex({
    site,
    vehicles,
    can,
}: VehiclesIndexProps) {
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
                            Модели из каталога платформы и ваши предложения с
                            ценами.
                        </p>
                    </div>
                    {can.editVehicles && (
                        <Button asChild>
                            <Link href={create(site.public_id)}>
                                <Plus aria-hidden="true" />
                                Добавить автомобиль
                            </Link>
                        </Button>
                    )}
                </header>

                {vehicles.length === 0 ? (
                    <Card className="border-dashed">
                        <CardHeader>
                            <h2 className="leading-none font-semibold">
                                Автомобилей пока нет
                            </h2>
                            <CardDescription>
                                Выберите марку, модель, поколение и серию из
                                каталога, затем добавьте предложения с ценами.
                            </CardDescription>
                        </CardHeader>
                    </Card>
                ) : (
                    <ul className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                        {vehicles.map((vehicle) => (
                            <li key={vehicle.public_id}>
                                <Link
                                    href={show({
                                        site: site.public_id,
                                        vehicle: vehicle.public_id,
                                    })}
                                    className="flex h-full min-w-0 flex-col gap-3 rounded-xl border bg-card p-4 shadow-sm transition-colors hover:bg-accent focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none"
                                >
                                    <div className="flex items-start gap-3">
                                        <Car
                                            aria-hidden="true"
                                            className="mt-0.5 size-5 shrink-0 text-muted-foreground"
                                        />
                                        <div className="min-w-0 flex-1">
                                            <h2 className="font-semibold break-words">
                                                {vehicle.catalog?.title ??
                                                    'Модель недоступна'}
                                            </h2>
                                            {vehicle.catalog && (
                                                <p className="text-sm text-muted-foreground">
                                                    {`${vehicle.catalog.generation} · ${vehicle.catalog.series}`}
                                                </p>
                                            )}
                                        </div>
                                        {!vehicle.status && (
                                            <Badge variant="secondary">
                                                Скрыт
                                            </Badge>
                                        )}
                                    </div>
                                    <p className="text-sm text-muted-foreground">
                                        {`Предложений: ${vehicle.offers_count} · Вариантов изображений: ${vehicle.media_sets_count}`}
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
            </main>
        </>
    );
}

VehiclesIndex.layout = {
    breadcrumbs: [{ title: 'Все сайты', href: dashboard() }],
};
