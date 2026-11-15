import { Head, Link, router } from '@inertiajs/react';
import { Plus, Search, Warehouse } from 'lucide-react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { dashboard } from '@/routes';
import { create, index as stockIndex, show } from '@/routes/workspace/stock';

type Option = { value: string; label: string };

type StockCard = {
    public_id: string;
    title: string;
    mark: string | null;
    model: string | null;
    body_name: string | null;
    stock_number: string;
    inner_id: string | null;
    path_key: string;
    condition: string;
    condition_label: string;
    status: string;
    status_label: string;
    year: number;
    color_name: string;
    mileage_km: number | null;
    availability: string;
    availability_label: string;
    price: string | null;
    photos_count: number;
};

type Props = {
    vehicles: StockCard[];
    filters: {
        condition: string | null;
        status: string | null;
        availability: string | null;
        q: string | null;
    };
    filter_options: {
        conditions: Option[];
        statuses: Option[];
        availabilities: Option[];
    };
};

const ALL = '__all__';

export default function StockIndex({
    vehicles,
    filters,
    filter_options,
}: Props) {
    const [q, setQ] = useState(filters.q ?? '');

    const apply = (next: Partial<Props['filters']>) => {
        router.get(
            stockIndex.url(),
            {
                condition: next.condition ?? filters.condition ?? undefined,
                status: next.status ?? filters.status ?? undefined,
                availability:
                    next.availability ?? filters.availability ?? undefined,
                q:
                    next.q === undefined
                        ? (filters.q ?? undefined)
                        : next.q || undefined,
            },
            { preserveState: true, replace: true },
        );
    };

    const search = (event: FormEvent) => {
        event.preventDefault();
        apply({ q: q.trim() });
    };

    return (
        <>
            <Head title="Склад" />
            <main className="flex min-w-0 flex-1 flex-col">
                <header className="flex flex-wrap items-start justify-between gap-4 px-4 pt-4 sm:px-6 sm:pt-6">
                    <div className="space-y-1">
                        <h1 className="text-2xl font-semibold tracking-tight sm:text-3xl">
                            Склад
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Автомобили для разделов с пробегом и
                            спецпредложений.
                        </p>
                    </div>
                    <Button asChild>
                        <Link href={create()}>
                            <Plus className="size-4" />
                            Добавить автомобиль
                        </Link>
                    </Button>
                </header>

                <div className="flex flex-col gap-4 px-4 py-6 sm:px-6">
                    <form
                        onSubmit={search}
                        className="grid gap-3 rounded-xl border p-4 sm:grid-cols-2 lg:grid-cols-5"
                    >
                        <div className="relative sm:col-span-2 lg:col-span-2">
                            <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                            <Input
                                value={q}
                                onChange={(event) => setQ(event.target.value)}
                                placeholder="Номер, цвет, кузов…"
                                className="pl-9"
                                aria-label="Поиск по складу"
                            />
                        </div>
                        <Select
                            value={filters.condition ?? ALL}
                            onValueChange={(value) =>
                                apply({
                                    condition: value === ALL ? null : value,
                                })
                            }
                        >
                            <SelectTrigger aria-label="Тип">
                                <SelectValue placeholder="Тип" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value={ALL}>Все типы</SelectItem>
                                {filter_options.conditions.map((option) => (
                                    <SelectItem
                                        key={option.value}
                                        value={option.value}
                                    >
                                        {option.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <Select
                            value={filters.status ?? ALL}
                            onValueChange={(value) =>
                                apply({
                                    status: value === ALL ? null : value,
                                })
                            }
                        >
                            <SelectTrigger aria-label="Статус">
                                <SelectValue placeholder="Статус" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value={ALL}>Все статусы</SelectItem>
                                {filter_options.statuses.map((option) => (
                                    <SelectItem
                                        key={option.value}
                                        value={option.value}
                                    >
                                        {option.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <Select
                            value={filters.availability ?? ALL}
                            onValueChange={(value) =>
                                apply({
                                    availability: value === ALL ? null : value,
                                })
                            }
                        >
                            <SelectTrigger aria-label="Наличие">
                                <SelectValue placeholder="Наличие" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value={ALL}>
                                    Любое наличие
                                </SelectItem>
                                {filter_options.availabilities.map((option) => (
                                    <SelectItem
                                        key={option.value}
                                        value={option.value}
                                    >
                                        {option.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <Button type="submit" variant="secondary">
                            Найти
                        </Button>
                    </form>

                    {vehicles.length === 0 ? (
                        <div className="flex flex-col items-center justify-center gap-3 rounded-lg border border-dashed px-6 py-16 text-center">
                            <Warehouse
                                className="size-10 text-muted-foreground"
                                aria-hidden
                            />
                            <div className="space-y-1">
                                <p className="text-base font-medium">
                                    Ничего не найдено
                                </p>
                                <p className="text-sm text-muted-foreground">
                                    Измените фильтры или добавьте автомобиль на
                                    склад.
                                </p>
                            </div>
                            <Button asChild className="mt-2">
                                <Link href={create()}>Добавить автомобиль</Link>
                            </Button>
                        </div>
                    ) : (
                        <ul className="divide-y rounded-lg border">
                            {vehicles.map((vehicle) => (
                                <li key={vehicle.public_id}>
                                    <Link
                                        href={show(vehicle.public_id)}
                                        className="flex flex-wrap items-baseline justify-between gap-3 px-4 py-3 text-sm transition-colors hover:bg-accent/40"
                                    >
                                        <div className="space-y-1">
                                            <p className="font-medium">
                                                {vehicle.title} · {vehicle.year}
                                                {vehicle.body_name
                                                    ? ` · ${vehicle.body_name}`
                                                    : ''}
                                            </p>
                                            <div className="flex flex-wrap items-center gap-2 text-muted-foreground">
                                                <Badge variant="secondary">
                                                    {vehicle.condition_label}
                                                </Badge>
                                                <Badge variant="outline">
                                                    {vehicle.status_label}
                                                </Badge>
                                                <span>
                                                    {vehicle.availability_label}
                                                </span>
                                                <span>
                                                    {vehicle.color_name}
                                                </span>
                                                {vehicle.mileage_km !==
                                                    null && (
                                                    <span>
                                                        {vehicle.mileage_km.toLocaleString(
                                                            'ru-RU',
                                                        )}{' '}
                                                        км
                                                    </span>
                                                )}
                                                {vehicle.price && (
                                                    <span>{vehicle.price}</span>
                                                )}
                                                <span>
                                                    Фото: {vehicle.photos_count}
                                                </span>
                                            </div>
                                        </div>
                                        <p className="font-mono text-xs text-muted-foreground">
                                            {vehicle.path_key}
                                        </p>
                                    </Link>
                                </li>
                            ))}
                        </ul>
                    )}
                </div>
            </main>
        </>
    );
}

StockIndex.layout = {
    breadcrumbs: [
        { title: 'Все сайты', href: dashboard() },
        { title: 'Склад', href: stockIndex() },
    ],
};
