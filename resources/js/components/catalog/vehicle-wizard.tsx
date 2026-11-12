import { Link, router, useForm } from '@inertiajs/react';
import {
    ArrowLeft,
    ArrowRight,
    Check,
    ImagePlus,
    Images,
    Palette,
    Trash2,
} from 'lucide-react';
import { useEffect, useId, useMemo, useRef, useState } from 'react';
import type { FormEvent, ReactNode } from 'react';
import {
    CatalogImage,
    GenerationList,
    MarkGrid,
    ModelGrid,
} from '@/components/catalog/catalog-grids';
import { IMAGE_ACCEPT, Swatch } from '@/components/catalog/photo-parts';
import {
    offerPayload,
    TrimOfferPicker,
} from '@/components/catalog/trim-offer-picker';
import type { OfferPrices } from '@/components/catalog/trim-offer-picker';
import InputError from '@/components/input-error';
import { TextField } from '@/components/platform/form-fields';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import { cn } from '@/lib/utils';
import type { RouteDefinition } from '@/wayfinder';
import type {
    AngleValue,
    CatalogSeries,
    SeriesLibrary,
    Trim,
    VehicleCollection,
    WizardProps,
} from '@/types/catalog';

const STEPS = [
    'Марка',
    'Модель',
    'Кузов',
    'Фото',
    'Комплектации',
    'Итог',
] as const;

type Picked = NonNullable<WizardProps['picked']>;
type Query = { mark?: string; model?: string; series?: string };
type Prices = OfferPrices;

type WizardForm = {
    series: string;
    custom_name: string;
    colors: string[];
    gallery: string[];
    catalog_image: boolean;
    primary_angle: AngleValue | '';
    uploads: File[];
    offers: { equipment: string; price: string; rrp: string }[];
    collections: string[];
};

const FIELD_STEP: [RegExp, number][] = [
    [/^series/, 2],
    [/^(colors|gallery|catalog_image|primary_angle|uploads)/, 3],
    [/^offers/, 4],
    [/^(custom_name|collections)/, 5],
];

function readQuery(): Query {
    const params = new URLSearchParams(window.location.search);

    return {
        mark: params.get('mark') ?? undefined,
        model: params.get('model') ?? undefined,
        series: params.get('series') ?? undefined,
    };
}

function photoDefaults(picked: Picked) {
    const angles = picked.library.colors.flatMap((color) =>
        color.images.map((image) => image.angle),
    );

    return {
        colors: picked.library.colors.map((color) => color.public_id),
        gallery: picked.library.gallery.map((image) => image.public_id),
        catalog_image:
            picked.library.colors.length === 0 && picked.series.image !== null,
        primary_angle: angles.includes('front_3_4_left')
            ? ('front_3_4_left' as const)
            : (angles[0] ?? ('' as const)),
    };
}

/**
 * Add-vehicle wizard (ADR-010) for the customer Catalog and Sites. Steps 1–3 browse the
 * catalog through partial reloads; photos, trims and prices are collected client-side and
 * sent once. The server validates every catalog reference again.
 */
export function VehicleWizard({
    wizard,
    action,
    cancelHref,
    destination,
    withPrices,
    taken = [],
    collections,
    preselectedCollection = null,
}: {
    wizard: WizardProps;
    action: RouteDefinition<'post'>;
    cancelHref: string;
    destination: string;
    withPrices: boolean;
    taken?: string[];
    collections?: VehicleCollection[];
    preselectedCollection?: string | null;
}) {
    const initialQuery = useMemo(readQuery, []);
    const [query, setQuery] = useState<Query>(initialQuery);
    const [step, setStep] = useState(() =>
        wizard.picked ? 3 : initialQuery.model ? 2 : initialQuery.mark ? 1 : 0,
    );
    const [loading, setLoading] = useState(false);
    const [prices, setPrices] = useState<Prices>({});
    const [selected, setSelected] = useState<string[]>([]);
    const form = useForm<WizardForm>({
        series: wizard.picked?.series.ref ?? '',
        custom_name: '',
        ...(wizard.picked
            ? photoDefaults(wizard.picked)
            : {
                  colors: [],
                  gallery: [],
                  catalog_image: false,
                  primary_angle: '',
              }),
        uploads: [],
        offers: [],
        collections:
            preselectedCollection &&
            collections?.some((c) => c.public_id === preselectedCollection)
                ? [preselectedCollection]
                : [],
    });
    const picked = wizard.picked;
    const top = useRef<HTMLDivElement>(null);

    const go = (next: number) => {
        setStep(next);
        top.current?.scrollIntoView({ behavior: 'smooth', block: 'start' });
    };

    const browse = (
        next: Query,
        only: string[],
        nextStep: number,
        onPicked?: (picked: Picked) => void,
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

                go(nextStep);
            },
            onFinish: () => setLoading(false),
        });
    };

    const pickSeries = (ref: string) => {
        if (ref === picked?.series.ref) {
            go(3);

            return;
        }

        browse(
            { ...query, series: ref },
            ['picked', 'options', 'characteristics'],
            3,
            (value) => {
                form.setData((data) => ({
                    ...data,
                    series: value.series.ref,
                    ...photoDefaults(value),
                    uploads: [],
                }));
                setSelected([]);
                setPrices({});
            },
        );
    };

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.transform((data) => ({
            ...data,
            offers: offerPayload(selected, prices, withPrices),
            catalog_image: data.catalog_image ? 1 : 0,
        }));
        form.submit(action, {
            forceFormData: true,
            onError: (errors) => {
                const first = Object.keys(errors)[0] ?? '';
                const target = FIELD_STEP.find(([pattern]) =>
                    pattern.test(first),
                );

                if (target) {
                    go(target[1]);
                }
            },
        });
    };

    const canOpen = (index: number): boolean =>
        index === 0 ||
        (index === 1 && wizard.models !== null && query.mark !== undefined) ||
        (index === 2 &&
            wizard.generations !== null &&
            query.model !== undefined) ||
        (index >= 3 && picked !== null);

    const photoCount =
        form.data.colors.length +
        form.data.gallery.length +
        form.data.uploads.length +
        (form.data.catalog_image ? 1 : 0);

    return (
        <form onSubmit={submit} className="flex min-w-0 flex-1 flex-col">
            <div ref={top} className="scroll-mt-4" />
            <Stepper step={step} canOpen={canOpen} onOpen={go} />

            <div className="flex flex-1 flex-col gap-6 p-4 sm:p-6">
                {step === 0 && (
                    <StepShell
                        title="Выберите марку"
                        description="Начните вводить название или выберите из популярных."
                    >
                        <MarkGrid
                            marks={wizard.marks}
                            selected={(mark) => mark.url === query.mark}
                            onSelect={(mark) =>
                                mark.url === query.mark && wizard.models
                                    ? go(1)
                                    : browse({ mark: mark.url }, ['models'], 1)
                            }
                        />
                    </StepShell>
                )}

                {step === 1 && wizard.models && (
                    <StepShell
                        title={`Модель ${wizard.models.mark.name}`}
                        description="Выберите модель."
                    >
                        <ModelGrid
                            models={wizard.models.items}
                            selected={(model) => model.url === query.model}
                            onSelect={(model) =>
                                model.url === query.model && wizard.generations
                                    ? go(2)
                                    : browse(
                                          {
                                              mark: query.mark,
                                              model: model.url,
                                          },
                                          ['generations'],
                                          2,
                                      )
                            }
                        />
                    </StepShell>
                )}

                {step === 2 && wizard.generations && (
                    <StepShell
                        title={`Поколение и кузов ${wizard.generations.model.name}`}
                        description="Значки показывают, сколько цветов и фото уже есть в медиатеке."
                    >
                        <GenerationList
                            generations={wizard.generations.items}
                            showMedia
                            selected={(series) =>
                                series.ref === picked?.series.ref
                            }
                            disabled={(series) =>
                                taken.includes(series.ref)
                                    ? `${series.name} — уже есть на сайте`
                                    : null
                            }
                            onSelect={(series) => pickSeries(series.ref)}
                        />
                        {taken.length > 0 && (
                            <p className="text-xs text-muted-foreground">
                                Кузова, которые уже есть на сайте, недоступны
                                для выбора.
                            </p>
                        )}
                    </StepShell>
                )}

                {step === 3 && picked && (
                    <PhotoStep
                        picked={picked}
                        angles={wizard.angles}
                        data={form.data}
                        setData={form.setData}
                        errors={form.errors as Record<string, string>}
                    />
                )}

                {step === 4 && picked && (
                    <StepShell
                        title="Комплектации и цены"
                        description={
                            withPrices
                                ? 'Отметьте комплектации, которые продаёте. Цены можно не указывать — тогда будет «Цена по запросу».'
                                : 'Отметьте комплектации. Цены укажет сотрудник с правом на цены.'
                        }
                    >
                        <TrimOfferPicker
                            trims={picked.trims}
                            options={wizard.options}
                            characteristics={wizard.characteristics}
                            selected={selected}
                            onSelectedChange={setSelected}
                            prices={prices}
                            onPricesChange={setPrices}
                            withPrices={withPrices}
                            errors={form.errors as Record<string, string>}
                        />
                    </StepShell>
                )}

                {step === 5 && picked && (
                    <SummaryStep
                        series={picked.series}
                        library={picked.library}
                        trims={picked.trims}
                        data={form.data}
                        setData={form.setData}
                        errors={form.errors as Record<string, string>}
                        selected={selected}
                        prices={prices}
                        withPrices={withPrices}
                        collections={collections}
                        destination={destination}
                        photoCount={photoCount}
                    />
                )}
            </div>

            <footer className="sticky bottom-0 z-10 flex flex-wrap items-center gap-3 border-t bg-background/95 px-4 py-3 backdrop-blur sm:px-6">
                {picked ? (
                    <div className="flex min-w-0 flex-1 items-center gap-3">
                        <CatalogImage
                            src={picked.series.image}
                            alt=""
                            className="hidden h-10 w-16 shrink-0 rounded-md bg-muted object-cover sm:block"
                        />
                        <div className="min-w-0">
                            <p className="truncate text-sm font-medium">
                                {picked.series.title}
                            </p>
                            <p className="truncate text-xs text-muted-foreground">
                                Фото: {photoCount} · Комплектаций:{' '}
                                {selected.length}
                            </p>
                        </div>
                    </div>
                ) : (
                    <p className="flex-1 text-sm text-muted-foreground">
                        Шаг {step + 1} из {STEPS.length}
                    </p>
                )}
                <div className="flex gap-2">
                    {step === 0 ? (
                        <Button type="button" variant="outline" asChild>
                            <Link href={cancelHref}>Отмена</Link>
                        </Button>
                    ) : (
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => go(step - 1)}
                        >
                            <ArrowLeft aria-hidden="true" />
                            Назад
                        </Button>
                    )}
                    {step < STEPS.length - 1 ? (
                        <Button
                            key="next"
                            type="button"
                            disabled={!canOpen(step + 1) || loading}
                            onClick={() => go(step + 1)}
                        >
                            {loading && <Spinner />}
                            Далее
                            <ArrowRight aria-hidden="true" />
                        </Button>
                    ) : (
                        <Button
                            key="submit"
                            type="submit"
                            disabled={form.processing || !picked}
                        >
                            {form.processing ? (
                                <Spinner />
                            ) : (
                                <Check aria-hidden="true" />
                            )}
                            Добавить
                        </Button>
                    )}
                </div>
            </footer>
        </form>
    );
}

function Stepper({
    step,
    canOpen,
    onOpen,
}: {
    step: number;
    canOpen: (index: number) => boolean;
    onOpen: (index: number) => void;
}) {
    return (
        <nav aria-label="Шаги мастера" className="border-b px-4 py-3 sm:px-6">
            <ol className="flex gap-1 overflow-x-auto">
                {STEPS.map((label, index) => {
                    const done = index < step;
                    const current = index === step;

                    return (
                        <li
                            key={label}
                            className="flex shrink-0 items-center gap-1"
                        >
                            <button
                                type="button"
                                disabled={!canOpen(index)}
                                aria-current={current ? 'step' : undefined}
                                onClick={() => onOpen(index)}
                                className={cn(
                                    'flex items-center gap-2 rounded-full px-2.5 py-1.5 text-sm transition-colors outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50 disabled:opacity-50',
                                    current
                                        ? 'bg-primary/10 font-medium text-foreground'
                                        : 'text-muted-foreground hover:bg-accent',
                                )}
                            >
                                <span
                                    className={cn(
                                        'flex size-6 items-center justify-center rounded-full border text-xs',
                                        current &&
                                            'border-primary bg-primary text-primary-foreground',
                                        done && 'border-primary text-primary',
                                    )}
                                >
                                    {done ? (
                                        <Check
                                            className="size-3.5"
                                            aria-hidden="true"
                                        />
                                    ) : (
                                        index + 1
                                    )}
                                </span>
                                {label}
                            </button>
                            {index < STEPS.length - 1 && (
                                <span
                                    className="h-px w-4 bg-border"
                                    aria-hidden="true"
                                />
                            )}
                        </li>
                    );
                })}
            </ol>
        </nav>
    );
}

function StepShell({
    title,
    description,
    aside,
    children,
}: {
    title: string;
    description: string;
    aside?: ReactNode;
    children: ReactNode;
}) {
    return (
        <section
            className="flex flex-col gap-4"
            aria-labelledby="wizard-step-title"
        >
            <div className="flex flex-wrap items-end justify-between gap-3">
                <div className="space-y-1">
                    <h2
                        id="wizard-step-title"
                        className="text-xl font-semibold tracking-tight"
                    >
                        {title}
                    </h2>
                    <p className="text-sm text-muted-foreground">
                        {description}
                    </p>
                </div>
                {aside}
            </div>
            {children}
        </section>
    );
}

type SetData = ReturnType<typeof useForm<WizardForm>>['setData'];

function toggle(list: string[], value: string, on: boolean): string[] {
    return on
        ? [...new Set([...list, value])]
        : list.filter((item) => item !== value);
}

function PhotoStep({
    picked,
    angles,
    data,
    setData,
    errors,
}: {
    picked: Picked;
    angles: WizardProps['angles'];
    data: WizardForm;
    setData: SetData;
    errors: Record<string, string>;
}) {
    const id = useId();
    const input = useRef<HTMLInputElement>(null);
    const { library, series } = picked;
    const previews = useMemo(
        () => data.uploads.map((file) => URL.createObjectURL(file)),
        [data.uploads],
    );

    useEffect(
        () => () => previews.forEach((url) => URL.revokeObjectURL(url)),
        [previews],
    );

    const uploadErrors = Object.entries(errors)
        .filter(([key]) => key.startsWith('uploads'))
        .map(([, message]) => message);

    return (
        <StepShell
            title="Фото"
            description="Фото копируются к вашему автомобилю. Потом их можно менять, не затрагивая медиатеку."
        >
            <section
                className="flex flex-col gap-3"
                aria-labelledby={`${id}-colors`}
            >
                <div className="flex flex-wrap items-center justify-between gap-2">
                    <h3
                        id={`${id}-colors`}
                        className="flex items-center gap-2 font-medium"
                    >
                        <Palette className="size-4" aria-hidden="true" />
                        Цвета из медиатеки
                    </h3>
                    {library.colors.length > 1 && (
                        <Button
                            type="button"
                            variant="ghost"
                            size="sm"
                            onClick={() =>
                                setData(
                                    'colors',
                                    data.colors.length === library.colors.length
                                        ? []
                                        : library.colors.map(
                                              (color) => color.public_id,
                                          ),
                                )
                            }
                        >
                            {data.colors.length === library.colors.length
                                ? 'Снять все'
                                : 'Выбрать все'}
                        </Button>
                    )}
                </div>
                {library.colors.length === 0 ? (
                    <Alert>
                        <AlertDescription>
                            Для этого кузова в медиатеке пока нет цветов.
                            Добавьте фото из каталога или загрузите свои.
                        </AlertDescription>
                    </Alert>
                ) : (
                    <div className="grid gap-3 lg:grid-cols-2">
                        {library.colors.map((color) => {
                            const checked = data.colors.includes(
                                color.public_id,
                            );
                            const byAngle = new Map(
                                color.images.map((image) => [
                                    image.angle,
                                    image,
                                ]),
                            );

                            return (
                                <label
                                    key={color.public_id}
                                    className={cn(
                                        'flex cursor-pointer flex-col gap-3 rounded-xl border bg-card p-3 shadow-xs transition-colors hover:bg-accent/40',
                                        checked &&
                                            'border-primary ring-1 ring-primary',
                                    )}
                                >
                                    <span className="flex items-center gap-2">
                                        <Checkbox
                                            checked={checked}
                                            onCheckedChange={(value) =>
                                                setData(
                                                    'colors',
                                                    toggle(
                                                        data.colors,
                                                        color.public_id,
                                                        value === true,
                                                    ),
                                                )
                                            }
                                        />
                                        <Swatch hex={color.swatch_hex} />
                                        <span className="font-medium">
                                            {color.name}
                                        </span>
                                        <span className="ml-auto text-xs text-muted-foreground">
                                            {color.images.length} из{' '}
                                            {angles.length}
                                        </span>
                                    </span>
                                    <span className="grid grid-cols-5 gap-1.5">
                                        {angles.map((angle) => {
                                            const image = byAngle.get(
                                                angle.value,
                                            );

                                            return image ? (
                                                <img
                                                    key={angle.value}
                                                    src={image.url}
                                                    alt={`${color.name}, ${angle.label}`}
                                                    loading="lazy"
                                                    className={cn(
                                                        'aspect-[4/3] w-full rounded-md border bg-muted/40 object-contain',
                                                        data.primary_angle ===
                                                            angle.value &&
                                                            'ring-2 ring-primary',
                                                    )}
                                                />
                                            ) : (
                                                <span
                                                    key={angle.value}
                                                    className="aspect-[4/3] rounded-md border border-dashed bg-muted/30"
                                                    title={`${angle.label}: нет фото`}
                                                />
                                            );
                                        })}
                                    </span>
                                </label>
                            );
                        })}
                    </div>
                )}
            </section>

            {library.colors.length > 0 && (
                <section
                    className="flex flex-col gap-2"
                    aria-labelledby={`${id}-angle`}
                >
                    <h3 id={`${id}-angle`} className="font-medium">
                        Главный ракурс
                    </h3>
                    <p className="text-sm text-muted-foreground">
                        Показывается в карточках на сайте. Выделен рамкой на
                        превью.
                    </p>
                    <ToggleGroup
                        type="single"
                        variant="outline"
                        className="flex-wrap justify-start"
                        value={data.primary_angle}
                        onValueChange={(value) =>
                            setData('primary_angle', value as AngleValue | '')
                        }
                        aria-label="Главный ракурс"
                    >
                        {angles.map((angle) => (
                            <ToggleGroupItem
                                key={angle.value}
                                value={angle.value}
                                className="px-3"
                            >
                                {angle.label}
                            </ToggleGroupItem>
                        ))}
                    </ToggleGroup>
                </section>
            )}

            {library.gallery.length > 0 && (
                <section
                    className="flex flex-col gap-3"
                    aria-labelledby={`${id}-gallery`}
                >
                    <div className="flex flex-wrap items-center justify-between gap-2">
                        <h3
                            id={`${id}-gallery`}
                            className="flex items-center gap-2 font-medium"
                        >
                            <Images className="size-4" aria-hidden="true" />
                            Галерея медиатеки
                        </h3>
                        <Button
                            type="button"
                            variant="ghost"
                            size="sm"
                            onClick={() =>
                                setData(
                                    'gallery',
                                    data.gallery.length ===
                                        library.gallery.length
                                        ? []
                                        : library.gallery.map(
                                              (image) => image.public_id,
                                          ),
                                )
                            }
                        >
                            {data.gallery.length === library.gallery.length
                                ? 'Снять все'
                                : 'Выбрать все'}
                        </Button>
                    </div>
                    <div className="grid grid-cols-3 gap-2 sm:grid-cols-4 lg:grid-cols-6">
                        {library.gallery.map((image, index) => {
                            const checked = data.gallery.includes(
                                image.public_id,
                            );

                            return (
                                <label
                                    key={image.public_id}
                                    className={cn(
                                        'relative cursor-pointer overflow-hidden rounded-lg border',
                                        checked && 'ring-2 ring-primary',
                                    )}
                                >
                                    <img
                                        src={image.url}
                                        alt={`Фото галереи ${index + 1}`}
                                        loading="lazy"
                                        className="aspect-[4/3] w-full object-cover"
                                    />
                                    <Checkbox
                                        className="absolute top-1.5 left-1.5 bg-background"
                                        checked={checked}
                                        aria-label={`Фото галереи ${index + 1}`}
                                        onCheckedChange={(value) =>
                                            setData(
                                                'gallery',
                                                toggle(
                                                    data.gallery,
                                                    image.public_id,
                                                    value === true,
                                                ),
                                            )
                                        }
                                    />
                                </label>
                            );
                        })}
                    </div>
                </section>
            )}

            {series.image && (
                <div className="flex items-center gap-3 rounded-xl border p-3">
                    <Checkbox
                        id={`${id}-catalog-image`}
                        checked={data.catalog_image}
                        onCheckedChange={(value) =>
                            setData('catalog_image', value === true)
                        }
                    />
                    <CatalogImage
                        src={series.image}
                        alt=""
                        className="h-12 w-20 rounded-md bg-muted object-cover"
                    />
                    <Label
                        htmlFor={`${id}-catalog-image`}
                        className="flex flex-col items-start gap-0.5"
                    >
                        Добавить фото из каталога
                        <span className="text-xs font-normal text-muted-foreground">
                            Обложка кузова — попадёт в галерею.
                        </span>
                    </Label>
                </div>
            )}

            <section
                className="flex flex-col gap-3"
                aria-labelledby={`${id}-uploads`}
            >
                <div className="flex flex-wrap items-center justify-between gap-2">
                    <h3
                        id={`${id}-uploads`}
                        className="flex items-center gap-2 font-medium"
                    >
                        <ImagePlus className="size-4" aria-hidden="true" />
                        Свои фото
                    </h3>
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        disabled={data.uploads.length >= 20}
                        onClick={() => input.current?.click()}
                    >
                        <ImagePlus aria-hidden="true" />
                        Выбрать файлы
                    </Button>
                    <input
                        ref={input}
                        type="file"
                        multiple
                        accept={IMAGE_ACCEPT}
                        className="sr-only"
                        tabIndex={-1}
                        aria-hidden="true"
                        onChange={(event) => {
                            const files = Array.from(event.target.files ?? []);
                            event.target.value = '';
                            setData(
                                'uploads',
                                [...data.uploads, ...files].slice(0, 20),
                            );
                        }}
                    />
                </div>
                <p className="text-xs text-muted-foreground">
                    JPEG, PNG или WebP, до 20 файлов. Попадут в галерею — цвет и
                    ракурс можно назначить позже на странице автомобиля.
                </p>
                {data.uploads.length > 0 && (
                    <ul className="grid grid-cols-3 gap-2 sm:grid-cols-4 lg:grid-cols-6">
                        {data.uploads.map((file, index) => (
                            <li
                                key={`${file.name}-${index}`}
                                className="group relative overflow-hidden rounded-lg border"
                            >
                                <img
                                    src={previews[index]}
                                    alt={file.name}
                                    className="aspect-[4/3] w-full object-cover"
                                />
                                <Button
                                    type="button"
                                    size="icon"
                                    variant="secondary"
                                    className="absolute top-1.5 right-1.5 size-7"
                                    aria-label={`Убрать ${file.name}`}
                                    onClick={() =>
                                        setData(
                                            'uploads',
                                            data.uploads.filter(
                                                (_, position) =>
                                                    position !== index,
                                            ),
                                        )
                                    }
                                >
                                    <Trash2 aria-hidden="true" />
                                </Button>
                            </li>
                        ))}
                    </ul>
                )}
                {uploadErrors.map((message) => (
                    <InputError key={message} message={message} />
                ))}
            </section>
        </StepShell>
    );
}

function SummaryStep({
    series,
    library,
    trims,
    data,
    setData,
    errors,
    selected,
    prices,
    withPrices,
    collections,
    destination,
    photoCount,
}: {
    series: CatalogSeries;
    library: SeriesLibrary;
    trims: Trim[];
    data: WizardForm;
    setData: SetData;
    errors: Record<string, string>;
    selected: string[];
    prices: Prices;
    withPrices: boolean;
    collections?: VehicleCollection[];
    destination: string;
    photoCount: number;
}) {
    const id = useId();
    const names = new Map(
        trims.flatMap((trim) =>
            trim.equipments.map(
                (equipment) =>
                    [
                        equipment.key,
                        { name: equipment.name, trim: trim.label },
                    ] as const,
            ),
        ),
    );
    const colors = library.colors.filter((color) =>
        data.colors.includes(color.public_id),
    );

    return (
        <StepShell
            title="Проверьте и добавьте"
            description={`Автомобиль будет добавлен: ${destination}.`}
        >
            <div className="grid gap-6 lg:grid-cols-[minmax(0,1fr)_minmax(0,1.2fr)]">
                <div className="flex flex-col gap-4">
                    <div className="overflow-hidden rounded-xl border bg-card shadow-xs">
                        <CatalogImage
                            src={series.image}
                            alt=""
                            className="aspect-[16/9] w-full bg-muted object-cover"
                        />
                        <div className="flex flex-col gap-1 p-4">
                            <p className="font-semibold">{series.title}</p>
                            <p className="text-sm text-muted-foreground">
                                {[
                                    series.generation.name,
                                    series.generation.years,
                                ]
                                    .filter(Boolean)
                                    .join(' · ')}
                            </p>
                        </div>
                    </div>
                    <TextField
                        id={`${id}-name`}
                        label="Своё название"
                        hint="Необязательно. По умолчанию — название из каталога."
                        placeholder={series.title}
                        maxLength={120}
                        value={data.custom_name}
                        onChange={(event) =>
                            setData('custom_name', event.target.value)
                        }
                        error={errors.custom_name}
                    />
                    {collections && collections.length > 0 && (
                        <fieldset className="flex flex-col gap-2">
                            <legend className="mb-1 text-sm font-medium">
                                Подборки
                            </legend>
                            {collections.map((collection) => (
                                <div
                                    key={collection.public_id}
                                    className="flex items-center gap-2"
                                >
                                    <Checkbox
                                        id={`${id}-${collection.public_id}`}
                                        checked={data.collections.includes(
                                            collection.public_id,
                                        )}
                                        onCheckedChange={(value) =>
                                            setData(
                                                'collections',
                                                toggle(
                                                    data.collections,
                                                    collection.public_id,
                                                    value === true,
                                                ),
                                            )
                                        }
                                    />
                                    <Label
                                        htmlFor={`${id}-${collection.public_id}`}
                                        className="font-normal"
                                    >
                                        {collection.name}
                                    </Label>
                                </div>
                            ))}
                            <InputError message={errors.collections} />
                        </fieldset>
                    )}
                </div>

                <dl className="flex flex-col gap-4 rounded-xl border bg-card p-4 text-sm shadow-xs">
                    <div className="flex flex-col gap-1.5">
                        <dt className="font-medium">Фото: {photoCount}</dt>
                        <dd className="flex flex-wrap gap-1.5 text-muted-foreground">
                            {colors.map((color) => (
                                <Badge
                                    key={color.public_id}
                                    variant="outline"
                                    className="gap-1.5 font-normal"
                                >
                                    <Swatch
                                        hex={color.swatch_hex}
                                        className="size-3"
                                    />
                                    {color.name}
                                </Badge>
                            ))}
                            {data.gallery.length > 0 && (
                                <Badge variant="outline">
                                    Галерея: {data.gallery.length}
                                </Badge>
                            )}
                            {data.uploads.length > 0 && (
                                <Badge variant="outline">
                                    Свои: {data.uploads.length}
                                </Badge>
                            )}
                            {data.catalog_image && (
                                <Badge variant="outline">
                                    Фото из каталога
                                </Badge>
                            )}
                            {photoCount === 0 &&
                                'Без фото — можно добавить позже.'}
                        </dd>
                    </div>
                    <div className="flex flex-col gap-1.5">
                        <dt className="font-medium">
                            Комплектации: {selected.length}
                        </dt>
                        <dd>
                            {selected.length === 0 ? (
                                <span className="text-muted-foreground">
                                    Не выбраны — можно добавить позже.
                                </span>
                            ) : (
                                <ul className="divide-y">
                                    {selected.map((key) => (
                                        <li
                                            key={key}
                                            className="flex justify-between gap-3 py-1.5"
                                        >
                                            <span className="min-w-0">
                                                <span className="block truncate">
                                                    {names.get(key)?.name ??
                                                        key}
                                                </span>
                                                <span className="block truncate text-xs text-muted-foreground">
                                                    {names.get(key)?.trim}
                                                </span>
                                            </span>
                                            {withPrices && (
                                                <span className="shrink-0 text-right">
                                                    {prices[key]?.price
                                                        ? `${prices[key].price} ₽`
                                                        : 'Цена по запросу'}
                                                    {prices[key]?.rrp && (
                                                        <span className="block text-xs text-muted-foreground line-through">
                                                            {prices[key].rrp} ₽
                                                        </span>
                                                    )}
                                                </span>
                                            )}
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </dd>
                    </div>
                    <InputError message={errors.series} />
                </dl>
            </div>
        </StepShell>
    );
}
