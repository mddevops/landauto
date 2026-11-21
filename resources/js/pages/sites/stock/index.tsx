import { Head, Link } from '@inertiajs/react';
import { Plus, Warehouse } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { dashboard } from '@/routes';
import { create, show } from '@/routes/sites/stock';

type ListingCard = {
    public_id: string;
    title: string;
    year: number;
    condition_label: string;
    status_label: string;
    visible: boolean;
    stock_number: string;
    inner_id: string | null;
    price: string | null;
    price_from_stock: boolean;
    cover_url: string | null;
};

type Props = {
    site: { public_id: string; name: string };
    listings: ListingCard[];
    can: { editVehicles: boolean; editPrices: boolean };
};

export default function SiteStockIndex({ site, listings, can }: Props) {
    return (
        <>
            <Head title={`Со склада — ${site.name}`} />
            <main className="flex min-w-0 flex-1 flex-col gap-6 p-4 sm:p-6">
                <header className="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                    <div className="min-w-0 space-y-1">
                        <p className="truncate text-sm text-muted-foreground">
                            {site.name}
                        </p>
                        <h1 className="text-2xl font-semibold tracking-tight sm:text-3xl">
                            Со склада
                        </h1>
                        <p className="max-w-2xl text-sm text-muted-foreground">
                            Автомобили склада, которые показываются на этом
                            сайте. Цена и фото здесь не меняют карточку склада.
                        </p>
                    </div>
                    {can.editVehicles && (
                        <Button asChild>
                            <Link href={create(site.public_id)}>
                                <Plus aria-hidden="true" />
                                Добавить со склада
                            </Link>
                        </Button>
                    )}
                </header>

                {listings.length === 0 ? (
                    <div className="flex flex-col items-center gap-3 rounded-xl border border-dashed p-10 text-center">
                        <Warehouse
                            className="size-8 text-muted-foreground"
                            aria-hidden="true"
                        />
                        <p className="font-medium">
                            На этом сайте пока нет автомобилей со склада
                        </p>
                        <p className="max-w-md text-sm text-muted-foreground">
                            Выберите готовые машины склада. Черновик сам на сайт
                            не попадает.
                        </p>
                    </div>
                ) : (
                    <ul className="grid gap-3">
                        {listings.map((listing) => (
                            <li key={listing.public_id}>
                                <Link
                                    href={show.url({
                                        site: site.public_id,
                                        listing: listing.public_id,
                                    })}
                                    className="flex items-center gap-4 rounded-xl border p-3 transition-colors hover:bg-muted/40"
                                >
                                    {listing.cover_url ? (
                                        <img
                                            src={listing.cover_url}
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
                                            {listing.title} {listing.year}
                                        </p>
                                        <p className="truncate text-sm text-muted-foreground">
                                            {listing.condition_label}
                                            {' · '}
                                            {listing.inner_id ??
                                                listing.stock_number}
                                            {' · '}
                                            {listing.price ?? 'Цена не указана'}
                                            {listing.price_from_stock
                                                ? ' · цена склада'
                                                : ' · цена сайта'}
                                        </p>
                                    </div>
                                    <Badge
                                        variant={
                                            listing.visible
                                                ? 'secondary'
                                                : 'outline'
                                        }
                                    >
                                        {listing.visible
                                            ? 'На сайте'
                                            : listing.status_label}
                                    </Badge>
                                </Link>
                            </li>
                        ))}
                    </ul>
                )}
            </main>
        </>
    );
}

SiteStockIndex.layout = {
    breadcrumbs: [{ title: 'Все сайты', href: dashboard() }],
};
