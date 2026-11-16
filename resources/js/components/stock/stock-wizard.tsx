import { Link, router, useForm } from '@inertiajs/react';
import { ArrowRight, Car, X } from 'lucide-react';
import { useId, useMemo, useRef, useState } from 'react';
import type { FormEvent, ReactNode } from 'react';
import {
    CatalogImage,
    GenerationList,
    MarkGrid,
    ModelGrid,
} from '@/components/catalog/catalog-grids';
import InputError from '@/components/input-error';
import { STOCK_COLORS } from '@/components/stock/stock-colors';
import {
    STOCK_PHOTO_MAX_FILES_WIZARD,
    StockPhotoUpload,
} from '@/components/stock/stock-photo-upload';
import { TextField } from '@/components/platform/form-fields';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import { cn } from '@/lib/utils';
import type { RouteDefinition } from '@/wayfinder';
import type { Trim, WizardProps } from '@/types/catalog';

type StepId =
    | 'type'
    | 'characteristics'
    | 'mileage'
    | 'photos'
    | 'equipment'
    | 'description'
    | 'price';

type Query = { mark?: string; model?: string; series?: string };
type Picked = NonNullable<WizardProps['picked']>;
type CatalogBrowse = 'mark' | 'model' | 'body' | null;

type FormData = {
    condition: 'new' | 'used' | '';
    series: string;
    equipment: string;
    year: string;
    body_name: string;
    color_id: string;
    color_name: string;
    color_swatch_hex: string;
    mileage_km: string;
    owners_count: string;
    vin: string;
    availability: 'in_stock' | 'on_order' | 'in_transit';
    price: string;
    inner_id: string;
    photos_later: boolean;
    pts_document_type: string;
    purchased_year: string;
    purchased_month: string;
    on_warranty: boolean;
    not_customs_cleared: boolean;
    description: string;
    uploads: File[];
};

const EMPTY_FORM: FormData = {
    condition: '',
    series: '',
    equipment: '',
    year: '',
    body_name: '',
    color_id: '',
    color_name: '',
    color_swatch_hex: '',
    mileage_km: '',
    owners_count: '',
    vin: '',
    availability: 'in_stock',
    price: '',
    inner_id: '',
    photos_later: false,
    pts_document_type: '',
    purchased_year: '',
    purchased_month: '',
    on_warranty: false,
    not_customs_cleared: false,
    description: '',
    uploads: [],
};

function readQuery(): Query {
    const params = new URLSearchParams(window.location.search);

    return {
        mark: params.get('mark') ?? undefined,
        model: params.get('model') ?? undefined,
        series: params.get('series') ?? undefined,
    };
}

function yearOptions(
    from: number | null | undefined,
    to: number | null | undefined,
): number[] {
    const current = new Date().getFullYear() + 1;
    const start = from ?? Math.max(1950, current - 30);
    const end = to ?? current;
    const years: number[] = [];

    for (let year = end; year >= start; year -= 1) {
        years.push(year);
    }

    return years.length > 0 ? years : [current];
}

function trimSpec(trim: Trim, label: string): string | null {
    return trim.specs.find((spec) => spec.label === label)?.value ?? null;
}

function filterTrims(
    trims: Trim[],
    engine: string | null,
    drive: string | null,
    gearbox: string | null,
): Trim[] {
    return trims.filter((trim) => {
        if (engine && trimSpec(trim, 'Двигатель') !== engine) {
            return false;
        }
        if (drive && trimSpec(trim, 'Привод') !== drive) {
            return false;
        }
        if (gearbox && trimSpec(trim, 'Коробка передач') !== gearbox) {
            return false;
        }

        return true;
    });
}

function collectOptions(trims: Trim[], label: string): string[] {
    return [
        ...new Set(
            trims
                .map((trim) => trimSpec(trim, label))
                .filter((value): value is string => value !== null),
        ),
    ];
}

function requiredMark(label: string): ReactNode {
    return (
        <>
            {label}{' '}
            <span className="text-destructive" aria-hidden>
                *
            </span>
        </>
    );
}

export function StockWizard({
    wizard,
    action,
    cancelHref,
}: {
    wizard: WizardProps;
    action: RouteDefinition<'post'>;
    cancelHref: string;
}) {
    const id = useId();
    const initialQuery = useMemo(readQuery, []);
    const [query, setQuery] = useState<Query>(initialQuery);
    const [step, setStep] = useState<StepId>(() =>
        wizard.picked
            ? 'characteristics'
            : initialQuery.model || initialQuery.mark
              ? 'characteristics'
              : 'type',
    );
    const [browse, setBrowse] = useState<CatalogBrowse>(() => {
        if (wizard.picked) {
            return null;
        }
        if (initialQuery.model) {
            return 'body';
        }
        if (initialQuery.mark) {
            return 'model';
        }

        return null;
    });
    const [loading, setLoading] = useState(false);
    const [modification, setModification] = useState<string | null>(null);
    const [engine, setEngine] = useState<string | null>(null);
    const [drive, setDrive] = useState<string | null>(null);
    const [gearbox, setGearbox] = useState<string | null>(null);
    const top = useRef<HTMLDivElement>(null);
    const form = useForm<FormData>({
        ...EMPTY_FORM,
        series: wizard.picked?.series.ref ?? '',
        body_name: wizard.picked?.series.name ?? '',
    });

    const picked = wizard.picked;
    const trims = picked?.trims ?? [];
    const filtered = filterTrims(trims, engine, drive, gearbox);
    const engines = collectOptions(trims, 'Двигатель');
    const drives = collectOptions(trims, 'Привод');
    const gearboxes = collectOptions(trims, 'Коробка передач');
    const years = yearOptions(
        picked?.series.generation.year_from,
        picked?.series.generation.year_to,
    );
    const selectedTrim =
        filtered.find((trim) => trim.key === modification) ?? null;
    const isUsed = form.data.condition === 'used';

    const navSteps = useMemo(() => {
        const items: { id: StepId; label: string; required?: boolean }[] = [
            { id: 'type', label: 'Тип', required: true },
            {
                id: 'characteristics',
                label: 'Характеристики',
                required: true,
            },
        ];

        if (isUsed) {
            items.push({ id: 'mileage', label: 'Пробег', required: true });
        }

        items.push(
            { id: 'photos', label: 'Фото' },
            { id: 'equipment', label: 'Комплектация' },
            { id: 'description', label: 'Описание' },
            { id: 'price', label: 'Цена', required: true },
        );

        return items;
    }, [isUsed]);

    const go = (next: StepId) => {
        setStep(next);
        top.current?.scrollIntoView({ behavior: 'smooth', block: 'start' });
    };

    const browseCatalog = (
        next: Query,
        only: string[],
        nextBrowse: CatalogBrowse,
        onPicked?: (value: Picked) => void,
    ) => {
        setLoading(true);
        setQuery(next);
        router.get(window.location.pathname, next, {
            only,
            preserveState: true,
            preserveScroll: true,
            replace: true,
            onSuccess: (page) => {
                const value = (page.props as unknown as WizardProps).picked;

                if (onPicked && value) {
                    onPicked(value);
                }

                setBrowse(nextBrowse);
                go('characteristics');
            },
            onFinish: () => setLoading(false),
        });
    };

    const pickSeries = (ref: string, bodyName: string) => {
        if (ref === picked?.series.ref) {
            setBrowse(null);

            return;
        }

        browseCatalog({ ...query, series: ref }, ['picked'], null, (value) => {
            form.setData((data) => ({
                ...data,
                series: value.series.ref,
                body_name: bodyName || value.series.name,
                equipment: '',
                year: '',
                color_id: '',
                color_name: '',
                color_swatch_hex: '',
            }));
            setModification(null);
            setEngine(null);
            setDrive(null);
            setGearbox(null);
        });
    };

    const resetAll = () => {
        form.setData({ ...EMPTY_FORM });
        setModification(null);
        setEngine(null);
        setDrive(null);
        setGearbox(null);
        setBrowse(null);
        setQuery({});
        setLoading(true);
        router.get(
            window.location.pathname,
            {},
            {
                only: ['marks', 'models', 'generations', 'picked'],
                preserveState: false,
                replace: true,
                onFinish: () => {
                    setLoading(false);
                    go('type');
                },
            },
        );
    };

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.transform((data) => ({
            ...data,
            photos_later: data.photos_later ? 1 : 0,
            on_warranty: data.on_warranty ? 1 : 0,
            not_customs_cleared: data.not_customs_cleared ? 1 : 0,
        }));
        form.submit(action, {
            forceFormData: true,
            onError: (errors) => {
                const first = Object.keys(errors)[0] ?? '';
                if (/condition/.test(first)) {
                    go('type');
                } else if (/series|year|color|equipment|body/.test(first)) {
                    go('characteristics');
                } else if (
                    /mileage|owners|vin|pts|warranty|customs/.test(first)
                ) {
                    go(isUsed ? 'mileage' : 'characteristics');
                } else if (/uploads|photos/.test(first)) {
                    go('photos');
                } else if (/description/.test(first)) {
                    go('description');
                } else {
                    go('price');
                }
            },
        });
    };

    const canOpen = (target: StepId): boolean => {
        if (target === 'type') {
            return true;
        }
        if (form.data.condition === '') {
            return false;
        }
        if (target === 'characteristics') {
            return true;
        }
        if (!picked) {
            return false;
        }
        if (target === 'mileage') {
            return isUsed;
        }

        return true;
    };

    const characteristicsReady =
        picked !== null &&
        form.data.year !== '' &&
        form.data.color_name !== '' &&
        modification !== null;

    const subtitle = (() => {
        if (!picked) {
            return null;
        }

        const parts = [
            form.data.year || null,
            form.data.body_name || picked.series.name,
            picked.series.generation.years
                ? `${picked.series.generation.years}, ${picked.series.generation.name}`
                : picked.series.generation.name,
            selectedTrim?.label ?? null,
        ].filter(Boolean);

        return parts.join(' · ');
    })();

    return (
        <form
            onSubmit={submit}
            className="flex min-h-[70vh] min-w-0 flex-1 flex-col lg:flex-row"
        >
            <aside className="flex w-full shrink-0 flex-col border-b bg-muted/30 px-4 py-5 lg:w-56 lg:border-r lg:border-b-0 lg:px-5">
                <nav className="flex gap-1 overflow-x-auto lg:flex-col lg:gap-0.5 lg:overflow-visible">
                    {navSteps.map((item) => {
                        const active = step === item.id;
                        const open = canOpen(item.id);

                        return (
                            <button
                                key={item.id}
                                type="button"
                                disabled={!open}
                                onClick={() => open && go(item.id)}
                                className={cn(
                                    'rounded-md px-3 py-2 text-left text-sm whitespace-nowrap transition-colors',
                                    active
                                        ? 'font-medium text-destructive'
                                        : open
                                          ? 'text-muted-foreground hover:text-foreground'
                                          : 'cursor-not-allowed text-muted-foreground/50',
                                )}
                            >
                                {item.label}
                                {item.required ? ' *' : ''}
                            </button>
                        );
                    })}
                </nav>
                <button
                    type="button"
                    onClick={resetAll}
                    className="mt-4 self-start text-sm text-primary hover:underline lg:mt-auto lg:pt-8"
                >
                    Сбросить всё
                </button>
                <Button
                    type="button"
                    variant="ghost"
                    className="mt-2 hidden justify-start px-3 lg:inline-flex"
                    asChild
                >
                    <Link href={cancelHref}>Отмена</Link>
                </Button>
            </aside>

            <div className="flex min-w-0 flex-1 justify-center px-4 py-6 sm:px-6">
                <div
                    ref={top}
                    className="w-full max-w-xl scroll-mt-4 space-y-4"
                >
                    {loading && (
                        <div className="flex justify-center py-2">
                            <Spinner className="size-5" />
                        </div>
                    )}

                    <div className="rounded-2xl border bg-card p-5 shadow-sm sm:p-7">
                        {step === 'type' && (
                            <StepCard title="Какой автомобиль добавляете?">
                                <div className="grid gap-3">
                                    {(
                                        [
                                            [
                                                'new',
                                                'Новый',
                                                'Со склада / спецпредложение',
                                            ],
                                            [
                                                'used',
                                                'С пробегом',
                                                'Пробег, владельцы и свои фото',
                                            ],
                                        ] as const
                                    ).map(([value, label, hint]) => (
                                        <button
                                            key={value}
                                            type="button"
                                            className={cn(
                                                'rounded-xl border px-4 py-4 text-left transition-colors hover:border-primary/40',
                                                form.data.condition === value &&
                                                    'border-primary ring-1 ring-primary',
                                            )}
                                            onClick={() => {
                                                form.setData(
                                                    'condition',
                                                    value,
                                                );
                                                setBrowse('mark');
                                                go('characteristics');
                                            }}
                                        >
                                            <p className="font-medium">
                                                {label}
                                            </p>
                                            <p className="mt-1 text-sm text-muted-foreground">
                                                {hint}
                                            </p>
                                        </button>
                                    ))}
                                </div>
                                <InputError message={form.errors.condition} />
                            </StepCard>
                        )}

                        {step === 'characteristics' && (
                            <StepCard>
                                {picked && (
                                    <header className="mb-5 flex items-start justify-between gap-3">
                                        <div className="min-w-0 space-y-1">
                                            <h2 className="text-xl font-semibold tracking-tight">
                                                {picked.series.title}
                                            </h2>
                                            {subtitle && (
                                                <p className="text-sm text-muted-foreground">
                                                    {subtitle}
                                                </p>
                                            )}
                                        </div>
                                        {picked.series.mark.logo && (
                                            <CatalogImage
                                                src={picked.series.mark.logo}
                                                alt=""
                                                fallback="logo"
                                                className="size-10 shrink-0 object-contain"
                                            />
                                        )}
                                    </header>
                                )}

                                {!picked && (
                                    <h2 className="mb-4 text-lg font-semibold">
                                        Характеристики
                                    </h2>
                                )}

                                <div className="mb-5 grid gap-2 sm:grid-cols-2">
                                    <ChipField
                                        label={requiredMark('Марка')}
                                        value={
                                            wizard.models?.mark.name ??
                                            (query.mark ? query.mark : null)
                                        }
                                        onOpen={() => setBrowse('mark')}
                                        onClear={() => {
                                            setQuery({});
                                            setBrowse('mark');
                                            form.setData((data) => ({
                                                ...data,
                                                series: '',
                                                body_name: '',
                                                equipment: '',
                                                year: '',
                                            }));
                                            setModification(null);
                                            router.get(
                                                window.location.pathname,
                                                {},
                                                {
                                                    only: [
                                                        'models',
                                                        'generations',
                                                        'picked',
                                                    ],
                                                    preserveState: true,
                                                    replace: true,
                                                },
                                            );
                                        }}
                                    />
                                    <ChipField
                                        label={requiredMark('Модель')}
                                        value={
                                            wizard.generations?.model.name ??
                                            null
                                        }
                                        disabled={!query.mark}
                                        onOpen={() =>
                                            query.mark && setBrowse('model')
                                        }
                                        onClear={() => {
                                            if (!query.mark) {
                                                return;
                                            }
                                            setQuery({ mark: query.mark });
                                            setBrowse('model');
                                            form.setData((data) => ({
                                                ...data,
                                                series: '',
                                                body_name: '',
                                                equipment: '',
                                                year: '',
                                            }));
                                            setModification(null);
                                            router.get(
                                                window.location.pathname,
                                                { mark: query.mark },
                                                {
                                                    only: [
                                                        'models',
                                                        'generations',
                                                        'picked',
                                                    ],
                                                    preserveState: true,
                                                    replace: true,
                                                },
                                            );
                                        }}
                                    />
                                </div>

                                {browse === 'mark' && (
                                    <div className="mb-5 space-y-3">
                                        <p className="text-sm font-medium">
                                            Выберите марку
                                        </p>
                                        <MarkGrid
                                            marks={wizard.marks}
                                            selected={(mark) =>
                                                mark.url === query.mark
                                            }
                                            onSelect={(mark) =>
                                                mark.url === query.mark &&
                                                wizard.models
                                                    ? setBrowse('model')
                                                    : browseCatalog(
                                                          {
                                                              mark: mark.url,
                                                          },
                                                          ['models'],
                                                          'model',
                                                      )
                                            }
                                        />
                                    </div>
                                )}

                                {browse === 'model' && wizard.models && (
                                    <div className="mb-5 space-y-3">
                                        <p className="text-sm font-medium">
                                            Модель {wizard.models.mark.name}
                                        </p>
                                        <ModelGrid
                                            models={wizard.models.items}
                                            selected={(model) =>
                                                model.url === query.model
                                            }
                                            onSelect={(model) =>
                                                model.url === query.model &&
                                                wizard.generations
                                                    ? setBrowse('body')
                                                    : browseCatalog(
                                                          {
                                                              mark: query.mark,
                                                              model: model.url,
                                                          },
                                                          ['generations'],
                                                          'body',
                                                      )
                                            }
                                        />
                                    </div>
                                )}

                                {browse === 'body' && wizard.generations && (
                                    <div className="mb-5 space-y-3">
                                        <p className="text-sm font-medium">
                                            Поколение и кузов
                                        </p>
                                        <GenerationList
                                            generations={
                                                wizard.generations.items
                                            }
                                            selected={(series) =>
                                                series.ref === query.series
                                            }
                                            onSelect={(series) =>
                                                pickSeries(
                                                    series.ref,
                                                    series.name,
                                                )
                                            }
                                        />
                                        <InputError
                                            message={form.errors.series}
                                        />
                                    </div>
                                )}

                                {picked && browse === null && (
                                    <div className="space-y-1">
                                        <SelectRow
                                            label={requiredMark('Год выпуска')}
                                            value={form.data.year || 'Выберите'}
                                            open={Boolean(form.data.year)}
                                        >
                                            <div className="grid grid-cols-4 gap-2 sm:grid-cols-5">
                                                {years.map((year) => (
                                                    <button
                                                        key={year}
                                                        type="button"
                                                        className={cn(
                                                            'rounded-lg border px-2 py-2 text-sm',
                                                            form.data.year ===
                                                                String(year) &&
                                                                'border-primary ring-1 ring-primary',
                                                        )}
                                                        onClick={() =>
                                                            form.setData(
                                                                'year',
                                                                String(year),
                                                            )
                                                        }
                                                    >
                                                        {year}
                                                    </button>
                                                ))}
                                            </div>
                                            <InputError
                                                message={form.errors.year}
                                            />
                                        </SelectRow>

                                        <SelectRow
                                            label={requiredMark('Кузов')}
                                            value={
                                                form.data.body_name ||
                                                picked.series.name
                                            }
                                            onEdit={() => setBrowse('body')}
                                        />

                                        <SelectRow
                                            label={requiredMark('Поколение')}
                                            value={
                                                picked.series.generation.years
                                                    ? `${picked.series.generation.years}, ${picked.series.generation.name}`
                                                    : picked.series.generation
                                                          .name
                                            }
                                            onEdit={() => setBrowse('body')}
                                        />

                                        {engines.length > 0 && (
                                            <SelectRow
                                                label={requiredMark(
                                                    'Двигатель',
                                                )}
                                                value={engine ?? 'Выберите'}
                                            >
                                                <ChipRow
                                                    values={engines}
                                                    selected={engine}
                                                    onSelect={setEngine}
                                                />
                                            </SelectRow>
                                        )}

                                        {drives.length > 0 && (
                                            <SelectRow
                                                label={requiredMark('Привод')}
                                                value={drive ?? 'Выберите'}
                                            >
                                                <ChipRow
                                                    values={drives}
                                                    selected={drive}
                                                    onSelect={setDrive}
                                                />
                                            </SelectRow>
                                        )}

                                        {gearboxes.length > 0 && (
                                            <SelectRow
                                                label={requiredMark(
                                                    'Коробка передач',
                                                )}
                                                value={gearbox ?? 'Выберите'}
                                            >
                                                <ChipRow
                                                    values={gearboxes}
                                                    selected={gearbox}
                                                    onSelect={setGearbox}
                                                />
                                            </SelectRow>
                                        )}

                                        <SelectRow
                                            label={requiredMark('Модификация')}
                                            value={
                                                selectedTrim?.label ??
                                                'Выберите'
                                            }
                                        >
                                            <div className="grid gap-2">
                                                {filtered.map((trim) => (
                                                    <button
                                                        key={trim.key}
                                                        type="button"
                                                        className={cn(
                                                            'rounded-xl border px-4 py-3 text-left text-sm transition-colors hover:border-primary/40',
                                                            modification ===
                                                                trim.key &&
                                                                'border-primary ring-1 ring-primary',
                                                        )}
                                                        onClick={() => {
                                                            setModification(
                                                                trim.key,
                                                            );
                                                            form.setData(
                                                                'equipment',
                                                                '',
                                                            );
                                                        }}
                                                    >
                                                        <p className="font-medium">
                                                            {trim.label}
                                                        </p>
                                                        <p className="text-muted-foreground">
                                                            {trim.summary}
                                                        </p>
                                                    </button>
                                                ))}
                                            </div>
                                            <InputError
                                                message={form.errors.equipment}
                                            />
                                        </SelectRow>

                                        <div className="border-t pt-4">
                                            <p className="mb-3 text-sm font-medium">
                                                {requiredMark('Цвет')}
                                            </p>
                                            <div className="flex flex-wrap gap-2.5">
                                                {STOCK_COLORS.map((color) => {
                                                    const selected =
                                                        form.data.color_id ===
                                                        color.id;

                                                    return (
                                                        <button
                                                            key={color.id}
                                                            type="button"
                                                            title={color.name}
                                                            aria-label={
                                                                color.name
                                                            }
                                                            aria-pressed={
                                                                selected
                                                            }
                                                            className={cn(
                                                                'size-8 rounded-full border-2 transition-transform',
                                                                selected
                                                                    ? 'scale-110 ring-2 ring-primary ring-offset-2'
                                                                    : 'hover:scale-105',
                                                            )}
                                                            style={{
                                                                background:
                                                                    color.gradient ??
                                                                    color.hex,
                                                                borderColor:
                                                                    color.hex ===
                                                                    '#FFFFFF'
                                                                        ? 'rgba(0,0,0,0.12)'
                                                                        : color.hex,
                                                                boxShadow:
                                                                    color.hex ===
                                                                    '#FFFFFF'
                                                                        ? '0 0 0 1px rgba(0,0,0,0.12)'
                                                                        : undefined,
                                                            }}
                                                            onClick={() =>
                                                                form.setData(
                                                                    (data) => ({
                                                                        ...data,
                                                                        color_id:
                                                                            color.id,
                                                                        color_name:
                                                                            color.name,
                                                                        color_swatch_hex:
                                                                            color.hex,
                                                                    }),
                                                                )
                                                            }
                                                        />
                                                    );
                                                })}
                                            </div>
                                            {form.data.color_name && (
                                                <p className="mt-2 text-sm text-muted-foreground">
                                                    {form.data.color_name}
                                                </p>
                                            )}
                                            <InputError
                                                message={form.errors.color_name}
                                            />
                                        </div>
                                    </div>
                                )}

                                <FooterNav
                                    onNext={() =>
                                        go(isUsed ? 'mileage' : 'photos')
                                    }
                                    nextDisabled={!characteristicsReady}
                                    nextLabel="Далее"
                                />
                            </StepCard>
                        )}

                        {step === 'mileage' && isUsed && (
                            <StepCard title="Пробег и история">
                                <TextField
                                    id={`${id}-mileage`}
                                    label="Пробег, км *"
                                    value={form.data.mileage_km}
                                    onChange={(event) =>
                                        form.setData(
                                            'mileage_km',
                                            event.target.value,
                                        )
                                    }
                                    error={form.errors.mileage_km}
                                />
                                <TextField
                                    id={`${id}-owners`}
                                    label="Владельцев по ПТС *"
                                    value={form.data.owners_count}
                                    onChange={(event) =>
                                        form.setData(
                                            'owners_count',
                                            event.target.value,
                                        )
                                    }
                                    error={form.errors.owners_count}
                                />
                                <TextField
                                    id={`${id}-vin`}
                                    label="VIN"
                                    value={form.data.vin}
                                    onChange={(event) =>
                                        form.setData(
                                            'vin',
                                            event.target.value.toUpperCase(),
                                        )
                                    }
                                    error={form.errors.vin}
                                />
                                <div className="space-y-2">
                                    <p className="text-sm font-medium">ПТС</p>
                                    <ToggleGroup
                                        type="single"
                                        variant="outline"
                                        value={form.data.pts_document_type}
                                        onValueChange={(value) =>
                                            form.setData(
                                                'pts_document_type',
                                                value || '',
                                            )
                                        }
                                    >
                                        <ToggleGroupItem value="original_or_electronic">
                                            Оригинал / ЭПТС
                                        </ToggleGroupItem>
                                        <ToggleGroupItem value="duplicate">
                                            Дубликат
                                        </ToggleGroupItem>
                                        <ToggleGroupItem value="none">
                                            Нет ПТС
                                        </ToggleGroupItem>
                                    </ToggleGroup>
                                    <div className="flex flex-wrap gap-4 pt-1">
                                        <label className="flex items-center gap-2 text-sm">
                                            <Checkbox
                                                checked={form.data.on_warranty}
                                                onCheckedChange={(checked) =>
                                                    form.setData(
                                                        'on_warranty',
                                                        checked === true,
                                                    )
                                                }
                                            />
                                            На гарантии
                                        </label>
                                        <label className="flex items-center gap-2 text-sm">
                                            <Checkbox
                                                checked={
                                                    form.data
                                                        .not_customs_cleared
                                                }
                                                onCheckedChange={(checked) =>
                                                    form.setData(
                                                        'not_customs_cleared',
                                                        checked === true,
                                                    )
                                                }
                                            />
                                            Не растаможен
                                        </label>
                                    </div>
                                </div>
                                <FooterNav
                                    onBack={() => go('characteristics')}
                                    onNext={() => go('photos')}
                                    nextDisabled={
                                        form.data.mileage_km === '' ||
                                        form.data.owners_count === ''
                                    }
                                />
                            </StepCard>
                        )}

                        {step === 'photos' && (
                            <StepCard title="Фото">
                                <div className="space-y-4">
                                    <StockPhotoUpload
                                        maxFiles={STOCK_PHOTO_MAX_FILES_WIZARD}
                                        onChange={(uploads) =>
                                            form.setData('uploads', uploads)
                                        }
                                    />
                                    <InputError message={form.errors.uploads} />
                                    <label className="flex items-center gap-2 text-sm">
                                        <Checkbox
                                            checked={form.data.photos_later}
                                            onCheckedChange={(checked) =>
                                                form.setData(
                                                    'photos_later',
                                                    checked === true,
                                                )
                                            }
                                        />
                                        Загрузить фото позже (черновик)
                                    </label>
                                </div>
                                <FooterNav
                                    onBack={() =>
                                        go(
                                            isUsed
                                                ? 'mileage'
                                                : 'characteristics',
                                        )
                                    }
                                    onNext={() => go('equipment')}
                                />
                            </StepCard>
                        )}

                        {step === 'equipment' && (
                            <StepCard title="Комплектация">
                                {!picked || !selectedTrim ? (
                                    <p className="text-sm text-muted-foreground">
                                        Сначала выберите модификацию в
                                        характеристиках.
                                    </p>
                                ) : (
                                    <div className="space-y-3">
                                        <p className="text-sm text-muted-foreground">
                                            Опции подтянутся из выбранной
                                            комплектации каталога.
                                        </p>
                                        <div className="flex flex-wrap gap-2">
                                            {selectedTrim.equipments.map(
                                                (item) => (
                                                    <button
                                                        key={item.key}
                                                        type="button"
                                                        className={cn(
                                                            'rounded-lg border px-3 py-2 text-sm',
                                                            form.data
                                                                .equipment ===
                                                                item.key &&
                                                                'border-primary ring-1 ring-primary',
                                                        )}
                                                        onClick={() =>
                                                            form.setData(
                                                                'equipment',
                                                                form.data
                                                                    .equipment ===
                                                                    item.key
                                                                    ? ''
                                                                    : item.key,
                                                            )
                                                        }
                                                    >
                                                        {item.name}
                                                    </button>
                                                ),
                                            )}
                                        </div>
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            className="px-0"
                                            onClick={() =>
                                                form.setData('equipment', '')
                                            }
                                        >
                                            Пропустить
                                        </Button>
                                        <InputError
                                            message={form.errors.equipment}
                                        />
                                    </div>
                                )}
                                <FooterNav
                                    onBack={() => go('photos')}
                                    onNext={() => go('description')}
                                />
                            </StepCard>
                        )}

                        {step === 'description' && (
                            <StepCard title="Описание">
                                <Textarea
                                    id={`${id}-description`}
                                    value={form.data.description}
                                    onChange={(event) =>
                                        form.setData(
                                            'description',
                                            event.target.value,
                                        )
                                    }
                                    rows={6}
                                    placeholder="Состояние, комплектация, история обслуживания…"
                                />
                                <InputError message={form.errors.description} />
                                <FooterNav
                                    onBack={() => go('equipment')}
                                    onNext={() => go('price')}
                                />
                            </StepCard>
                        )}

                        {step === 'price' && (
                            <StepCard title="Цена и наличие">
                                <TextField
                                    id={`${id}-price`}
                                    label="Цена, ₽"
                                    value={form.data.price}
                                    onChange={(event) =>
                                        form.setData(
                                            'price',
                                            event.target.value,
                                        )
                                    }
                                    error={form.errors.price}
                                />
                                <div className="space-y-2">
                                    <p className="text-sm font-medium">
                                        Наличие *
                                    </p>
                                    <ToggleGroup
                                        type="single"
                                        variant="outline"
                                        value={form.data.availability}
                                        onValueChange={(value) =>
                                            value &&
                                            form.setData(
                                                'availability',
                                                value as FormData['availability'],
                                            )
                                        }
                                    >
                                        <ToggleGroupItem value="in_stock">
                                            В наличии
                                        </ToggleGroupItem>
                                        <ToggleGroupItem value="on_order">
                                            На заказ
                                        </ToggleGroupItem>
                                        <ToggleGroupItem value="in_transit">
                                            В пути
                                        </ToggleGroupItem>
                                    </ToggleGroup>
                                </div>
                                <TextField
                                    id={`${id}-inner`}
                                    label="Складской номер"
                                    value={form.data.inner_id}
                                    onChange={(event) =>
                                        form.setData(
                                            'inner_id',
                                            event.target.value,
                                        )
                                    }
                                    error={form.errors.inner_id}
                                />
                                <div className="flex flex-wrap items-center justify-between gap-3 pt-2">
                                    <Button
                                        type="button"
                                        variant="outline"
                                        onClick={() => go('description')}
                                    >
                                        Назад
                                    </Button>
                                    <Button
                                        type="submit"
                                        disabled={form.processing}
                                    >
                                        {form.processing ? (
                                            <Spinner className="size-4" />
                                        ) : (
                                            <Car className="size-4" />
                                        )}
                                        Добавить на склад
                                    </Button>
                                </div>
                            </StepCard>
                        )}
                    </div>

                    <div className="lg:hidden">
                        <Button type="button" variant="ghost" asChild>
                            <Link href={cancelHref}>Отмена</Link>
                        </Button>
                    </div>
                </div>
            </div>
        </form>
    );
}

function StepCard({
    title,
    children,
}: {
    title?: string;
    children: ReactNode;
}) {
    return (
        <section className="space-y-5">
            {title && (
                <h2 className="text-lg font-semibold tracking-tight">
                    {title}
                </h2>
            )}
            {children}
        </section>
    );
}

function ChipField({
    label,
    value,
    disabled,
    onOpen,
    onClear,
}: {
    label: ReactNode;
    value: string | null;
    disabled?: boolean;
    onOpen: () => void;
    onClear: () => void;
}) {
    return (
        <div className="space-y-1.5">
            <p className="text-xs text-muted-foreground">{label}</p>
            {value ? (
                <div className="flex items-center gap-1 rounded-lg border bg-muted/40 px-3 py-2.5 text-sm">
                    <button
                        type="button"
                        className="min-w-0 flex-1 truncate text-left font-medium"
                        onClick={onOpen}
                    >
                        {value}
                    </button>
                    <button
                        type="button"
                        className="rounded p-0.5 text-muted-foreground hover:text-foreground"
                        onClick={onClear}
                    >
                        <X className="size-4" />
                        <span className="sr-only">Сбросить</span>
                    </button>
                </div>
            ) : (
                <button
                    type="button"
                    disabled={disabled}
                    onClick={onOpen}
                    className={cn(
                        'w-full rounded-lg border border-dashed px-3 py-2.5 text-left text-sm text-muted-foreground',
                        disabled
                            ? 'cursor-not-allowed opacity-50'
                            : 'hover:border-primary/40 hover:text-foreground',
                    )}
                >
                    Выберите
                </button>
            )}
        </div>
    );
}

function SelectRow({
    label,
    value,
    open,
    onEdit,
    children,
}: {
    label: ReactNode;
    value: string;
    open?: boolean;
    onEdit?: () => void;
    children?: ReactNode;
}) {
    const [expanded, setExpanded] = useState(Boolean(children) && !open);

    return (
        <div className="border-b last:border-b-0">
            <button
                type="button"
                className="flex w-full items-center justify-between gap-3 py-3 text-left"
                onClick={() => {
                    if (onEdit) {
                        onEdit();

                        return;
                    }
                    if (children) {
                        setExpanded((current) => !current);
                    }
                }}
            >
                <span className="text-sm text-muted-foreground">{label}</span>
                <span className="text-sm font-medium">{value}</span>
            </button>
            {children && expanded && <div className="pb-4">{children}</div>}
        </div>
    );
}

function ChipRow({
    values,
    selected,
    onSelect,
}: {
    values: string[];
    selected: string | null;
    onSelect: (value: string | null) => void;
}) {
    return (
        <div className="flex flex-wrap gap-2">
            {values.map((value) => (
                <button
                    key={value}
                    type="button"
                    className={cn(
                        'rounded-lg border px-3 py-2 text-sm',
                        selected === value &&
                            'border-primary ring-1 ring-primary',
                    )}
                    onClick={() => onSelect(selected === value ? null : value)}
                >
                    {value}
                </button>
            ))}
        </div>
    );
}

function FooterNav({
    onBack,
    onNext,
    nextDisabled,
    nextLabel = 'Далее',
}: {
    onBack?: () => void;
    onNext: () => void;
    nextDisabled?: boolean;
    nextLabel?: string;
}) {
    return (
        <div className="flex flex-wrap items-center justify-between gap-3 pt-2">
            {onBack ? (
                <Button type="button" variant="outline" onClick={onBack}>
                    Назад
                </Button>
            ) : (
                <span />
            )}
            <Button type="button" disabled={nextDisabled} onClick={onNext}>
                {nextLabel}
                <ArrowRight className="size-4" />
            </Button>
        </div>
    );
}
