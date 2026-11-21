import { Head, Link, router } from '@inertiajs/react';
import { Warehouse } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { dashboard } from '@/routes';
import { index, store } from '@/routes/sites/stock';

type StockCard = {
    public_id: string;
    title: string;
    year: number;
    condition_label: string;
    stock_number: string;
    inner_id: string | null;
    price: string | null;
    cover_url: string | null;
};

type Props = {
    site: { public_id: string; name: string };
    vehicles: StockCard[];
};

export default function SiteStockCreate({ site, vehicles }: Props) {
    const add = (stockVehicle: string) => {
        router.post(store.url(site.public_id), {
            stock_vehicle: stockVehicle,
        });
    };

    return (
        <>
            <Head title={`Добавить со склада — ${site.name}`} />
            <main className="flex min-w-0 flex-1 flex-col gap-6 p-4 sm:p-6">
                <header className="space-y-1">
                    <p className="truncate text-sm text-muted-foreground">
                        {site.name}
                    </p>
                    <h1 className="text-2xl font-semibold tracking-tight sm:text-3xl">
                        Добавить со склада
                    </h1>
                    <p className="max-w-2xl text-sm text-muted-foreground">
                        Готовые автомобили склада, которых ещё нет на этом
                        сайте. С пробегом попадут в раздел «С пробегом», новые —
                        в «Новые».
                    </p>
                </header>

                {vehicles.length === 0 ? (
                    <div className="flex flex-col items-center gap-3 rounded-xl border border-dashed p-10 text-center">
                        <Warehouse
                            className="size-8 text-muted-foreground"
                            aria-hidden="true"
                        />
                        <p className="font-medium">Нет готовых автомобилей</p>
                        <p className="max-w-md text-sm text-muted-foreground">
                            На складе нет готовых машин, которые ещё не
                            добавлены на этот сайт.
                        </p>
                        <Button variant="outline" asChild>
                            <Link href={index(site.public_id)}>
                                К списку сайта
                            </Link>
                        </Button>
                    </div>
                ) : (
                    <ul className="grid gap-3">
                        {vehicles.map((vehicle) => (
                            <li
                                key={vehicle.public_id}
                                className="flex items-center gap-4 rounded-xl border p-3"
                            >
                                {vehicle.cover_url ? (
                                    <img
                                        src={vehicle.cover_url}
                                        alt=""
                                        className="size-16 rounded-md object-cover"
                                    />
                                ) : (
                                    <div className="flex size-16 items-center justify-center rounded-md bg-muted text-muted-foreground">
                                        <Warehouse
                                            className="size-5"
                                            aria-hidden="true"
                                        />
                                    </div>
                                )}
                                <div className="min-w-0 flex-1 space-y-1">
                                    <p className="truncate font-medium">
                                        {vehicle.title} {vehicle.year}
                                    </p>
                                    <p className="truncate text-sm text-muted-foreground">
                                        {vehicle.condition_label}
                                        {' · '}
                                        {vehicle.inner_id ??
                                            vehicle.stock_number}
                                        {vehicle.price
                                            ? ` · ${vehicle.price}`
                                            : ''}
                                    </p>
                                </div>
                                <Button
                                    type="button"
                                    onClick={() => add(vehicle.public_id)}
                                >
                                    Добавить
                                </Button>
                            </li>
                        ))}
                    </ul>
                )}
            </main>
        </>
    );
}

SiteStockCreate.layout = {
    breadcrumbs: [{ title: 'Все сайты', href: dashboard() }],
};
