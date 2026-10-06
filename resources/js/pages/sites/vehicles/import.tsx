import { Head, Link, router, useForm } from '@inertiajs/react';
import { Copy } from 'lucide-react';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { dashboard } from '@/routes';
import { index } from '@/routes/sites/vehicles';
import { create, store } from '@/routes/sites/vehicles/imports';

type SourceVehicle = {
    public_id: string;
    title: string;
    subtitle: string | null;
    status: boolean;
    offers_count: number;
    benefits_count: number;
    conflict: boolean;
};

type CopyResult = {
    vehicle: string;
    title: string;
    result: 'copied' | 'skipped' | 'conflict' | 'failed';
};

type ImportProps = {
    site: { public_id: string; name: string };
    sources: { public_id: string; name: string }[];
    source: string | null;
    vehicles: SourceVehicle[];
    can: { copyPrices: boolean; copyBenefits: boolean };
    result: CopyResult[] | null;
};

const resultLabels: Record<CopyResult['result'], string> = {
    copied: 'Скопирован',
    skipped: 'Пропущен',
    conflict: 'Уже есть на сайте — пропущен',
    failed: 'Ошибка, ничего не изменено',
};

const checkboxClass = 'size-4 shrink-0 accent-primary';

export default function ImportVehicles({
    site,
    sources,
    source,
    vehicles,
    can,
    result,
}: ImportProps) {
    const form = useForm({
        source: source ?? '',
        vehicles: [] as string[],
        include_offers: false,
        include_benefits: false,
    });
    const copyable = vehicles.filter((vehicle) => !vehicle.conflict);

    function toggle(publicId: string) {
        form.setData(
            'vehicles',
            form.data.vehicles.includes(publicId)
                ? form.data.vehicles.filter((id) => id !== publicId)
                : [...form.data.vehicles, publicId],
        );
    }

    return (
        <>
            <Head title={`Импорт автомобилей — ${site.name}`} />
            <main className="flex min-w-0 flex-1 flex-col gap-6 p-4 sm:p-6">
                <header className="space-y-1">
                    <Link
                        href={index(site.public_id)}
                        className="text-sm text-muted-foreground hover:underline"
                    >
                        {`${site.name} · Автомобили`}
                    </Link>
                    <h1 className="text-2xl font-semibold tracking-tight sm:text-3xl">
                        Импортировать с другого сайта
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        На этот сайт создаются независимые копии. Изменения на
                        сайте-источнике потом не переносятся.
                    </p>
                </header>

                {result && result.length > 0 && (
                    <section
                        aria-labelledby="result-title"
                        className="space-y-2 rounded-xl border bg-card p-4 shadow-sm"
                    >
                        <h2 id="result-title" className="font-semibold">
                            Результат копирования
                        </h2>
                        <ul className="space-y-1 text-sm">
                            {result.map((item) => (
                                <li
                                    key={item.vehicle}
                                    className="flex flex-wrap gap-x-2"
                                >
                                    <span className="font-medium break-words">
                                        {item.title}
                                    </span>
                                    <span className="text-muted-foreground">
                                        {resultLabels[item.result]}
                                    </span>
                                </li>
                            ))}
                        </ul>
                    </section>
                )}

                {sources.length === 0 ? (
                    <p className="text-sm text-muted-foreground">
                        Нет других сайтов, с которых вы можете копировать
                        автомобили.
                    </p>
                ) : (
                    <form
                        className="flex flex-col gap-6"
                        onSubmit={(event) => {
                            event.preventDefault();
                            form.post(store.url(site.public_id), {
                                preserveScroll: true,
                                onSuccess: () => form.setData('vehicles', []),
                            });
                        }}
                    >
                        <div className="grid max-w-md gap-2">
                            <Label htmlFor="import-source">Сайт-источник</Label>
                            <select
                                id="import-source"
                                value={form.data.source}
                                onChange={(event) =>
                                    router.get(
                                        create.url(site.public_id, {
                                            query: {
                                                source: event.target.value,
                                            },
                                        }),
                                    )
                                }
                                className="h-9 w-full min-w-0 rounded-md border border-input bg-transparent px-3 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                            >
                                <option value="">Выберите сайт</option>
                                {sources.map((candidate) => (
                                    <option
                                        key={candidate.public_id}
                                        value={candidate.public_id}
                                    >
                                        {candidate.name}
                                    </option>
                                ))}
                            </select>
                            <InputError message={form.errors.source} />
                        </div>

                        {source && (
                            <>
                                <fieldset className="space-y-3">
                                    <legend className="mb-2 font-semibold">
                                        Автомобили
                                    </legend>
                                    {vehicles.length === 0 ? (
                                        <p className="text-sm text-muted-foreground">
                                            На этом сайте нет автомобилей.
                                        </p>
                                    ) : (
                                        <ul className="grid gap-2 lg:grid-cols-2">
                                            {vehicles.map((vehicle) => (
                                                <li key={vehicle.public_id}>
                                                    <label className="flex min-w-0 items-start gap-3 rounded-lg border bg-card p-3 has-[:disabled]:opacity-70">
                                                        <input
                                                            type="checkbox"
                                                            className={`${checkboxClass} mt-0.5`}
                                                            checked={form.data.vehicles.includes(
                                                                vehicle.public_id,
                                                            )}
                                                            onChange={() =>
                                                                toggle(
                                                                    vehicle.public_id,
                                                                )
                                                            }
                                                            disabled={
                                                                vehicle.conflict
                                                            }
                                                        />
                                                        <span className="min-w-0 flex-1 space-y-1">
                                                            <span className="block font-medium break-words">
                                                                {vehicle.title}
                                                            </span>
                                                            {vehicle.subtitle && (
                                                                <span className="block text-sm text-muted-foreground">
                                                                    {
                                                                        vehicle.subtitle
                                                                    }
                                                                </span>
                                                            )}
                                                            <span className="block text-xs text-muted-foreground">
                                                                {`Предложений: ${vehicle.offers_count} · Выгод: ${vehicle.benefits_count}`}
                                                            </span>
                                                        </span>
                                                        {vehicle.conflict && (
                                                            <Badge variant="secondary">
                                                                Уже есть на
                                                                сайте
                                                            </Badge>
                                                        )}
                                                    </label>
                                                </li>
                                            ))}
                                        </ul>
                                    )}
                                    <InputError
                                        message={form.errors.vehicles}
                                    />
                                </fieldset>

                                {copyable.length > 0 && (
                                    <fieldset className="space-y-2">
                                        <legend className="mb-2 font-semibold">
                                            Что копировать
                                        </legend>
                                        <p className="text-sm text-muted-foreground">
                                            Серия, название, описание, показ и
                                            выбранные цвета копируются всегда.
                                        </p>
                                        <label className="flex items-center gap-2 text-sm has-[:disabled]:opacity-60">
                                            <input
                                                type="checkbox"
                                                className={checkboxClass}
                                                checked={
                                                    form.data.include_offers
                                                }
                                                onChange={(event) => {
                                                    form.setData(
                                                        'include_offers',
                                                        event.target.checked,
                                                    );

                                                    if (!event.target.checked) {
                                                        form.setData(
                                                            'include_benefits',
                                                            false,
                                                        );
                                                    }
                                                }}
                                                disabled={!can.copyPrices}
                                            />
                                            Предложения с ценами, наличием и
                                            бейджами
                                        </label>
                                        <label className="flex items-center gap-2 text-sm has-[:disabled]:opacity-60">
                                            <input
                                                type="checkbox"
                                                className={checkboxClass}
                                                checked={
                                                    form.data.include_benefits
                                                }
                                                onChange={(event) =>
                                                    form.setData(
                                                        'include_benefits',
                                                        event.target.checked,
                                                    )
                                                }
                                                disabled={
                                                    !can.copyBenefits ||
                                                    !form.data.include_offers
                                                }
                                            />
                                            Выгоды предложений
                                        </label>
                                        {!can.copyPrices && (
                                            <p className="text-xs text-muted-foreground">
                                                Копирование цен недоступно для
                                                вашей роли.
                                            </p>
                                        )}
                                        <InputError
                                            message={
                                                form.errors.include_benefits
                                            }
                                        />
                                    </fieldset>
                                )}

                                <div>
                                    <Button
                                        type="submit"
                                        disabled={
                                            form.processing ||
                                            form.data.vehicles.length === 0
                                        }
                                    >
                                        {form.processing ? (
                                            <Spinner />
                                        ) : (
                                            <Copy aria-hidden="true" />
                                        )}
                                        Скопировать выбранные
                                    </Button>
                                </div>
                            </>
                        )}
                    </form>
                )}
            </main>
        </>
    );
}

ImportVehicles.layout = {
    breadcrumbs: [{ title: 'Все сайты', href: dashboard() }],
};
