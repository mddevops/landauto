import { router } from '@inertiajs/react';
import { CircleCheck, CirclePlus, Gauge, ListChecks } from 'lucide-react';
import { useState } from 'react';
import type { ReactNode } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Spinner } from '@/components/ui/spinner';
import type {
    CharacteristicSection,
    OptionSection,
    Trim,
    TrimEquipment,
} from '@/types/catalog';

type DetailsState =
    | { kind: 'options'; title: string; subtitle: string }
    | { kind: 'characteristics'; title: string; subtitle: string }
    | null;

/**
 * Modifications of a Series with their trims, as on the auto.ru series page. Options and
 * characteristics are loaded on demand through partial reloads of the `options` /
 * `characteristics` props.
 */
export function TrimsExplorer({
    trims,
    options,
    characteristics,
    renderEquipment,
    emptyText = 'У этого кузова пока нет модификаций в каталоге.',
}: {
    trims: Trim[];
    options: OptionSection[];
    characteristics?: CharacteristicSection[];
    renderEquipment?: (equipment: TrimEquipment, trim: Trim) => ReactNode;
    emptyText?: string;
}) {
    const [details, setDetails] = useState<DetailsState>(null);
    const [loading, setLoading] = useState(false);

    const open = (
        kind: 'options' | 'characteristics',
        param: 'equipment' | 'modification',
        key: string,
        title: string,
        subtitle: string,
    ) => {
        setDetails({ kind, title, subtitle });
        setLoading(true);
        router.reload({
            only: [kind],
            data: { [param]: key },
            preserveUrl: true,
            onFinish: () => setLoading(false),
        });
    };

    if (trims.length === 0) {
        return (
            <p className="rounded-xl border border-dashed p-8 text-center text-sm text-muted-foreground">
                {emptyText}
            </p>
        );
    }

    return (
        <>
            <div className="flex flex-col gap-3">
                {trims.map((trim) => (
                    <section
                        key={trim.key}
                        className="rounded-xl border bg-card shadow-xs"
                        aria-label={trim.label}
                    >
                        <div className="flex flex-col gap-2 border-b px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
                            <div className="flex min-w-0 flex-col gap-1">
                                <h3 className="font-semibold">{trim.label}</h3>
                                <div className="flex flex-wrap gap-1">
                                    {trim.specs.map((spec) => (
                                        <Badge
                                            key={spec.label}
                                            variant="outline"
                                            className="font-normal"
                                        >
                                            <span className="text-muted-foreground">
                                                {spec.label}:
                                            </span>
                                            {spec.value}
                                        </Badge>
                                    ))}
                                </div>
                            </div>
                            {characteristics !== undefined && (
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    className="self-start sm:self-center"
                                    onClick={() =>
                                        open(
                                            'characteristics',
                                            'modification',
                                            trim.key,
                                            'Характеристики',
                                            trim.label,
                                        )
                                    }
                                >
                                    <Gauge aria-hidden="true" />
                                    Характеристики
                                </Button>
                            )}
                        </div>
                        <ul className="divide-y">
                            {trim.equipments.map((equipment) => (
                                <li
                                    key={equipment.key}
                                    className="flex flex-col gap-2 px-4 py-2.5 sm:flex-row sm:items-center"
                                >
                                    <div className="flex min-w-0 flex-1 items-center gap-2">
                                        <span className="truncate font-medium">
                                            {equipment.name}
                                        </span>
                                        {equipment.options_count > 0 && (
                                            <Button
                                                variant="link"
                                                size="sm"
                                                className="h-auto px-1 text-muted-foreground"
                                                onClick={() =>
                                                    open(
                                                        'options',
                                                        'equipment',
                                                        equipment.key,
                                                        `Опции: ${equipment.name}`,
                                                        trim.label,
                                                    )
                                                }
                                            >
                                                <ListChecks aria-hidden="true" />
                                                Опций: {equipment.options_count}
                                            </Button>
                                        )}
                                    </div>
                                    {renderEquipment?.(equipment, trim)}
                                </li>
                            ))}
                        </ul>
                    </section>
                ))}
            </div>

            <Dialog
                open={details !== null}
                onOpenChange={(value) => !value && setDetails(null)}
            >
                <DialogContent className="max-h-[85vh] overflow-y-auto sm:max-w-2xl">
                    <DialogHeader>
                        <DialogTitle>{details?.title}</DialogTitle>
                        <DialogDescription>
                            {details?.subtitle}
                        </DialogDescription>
                    </DialogHeader>
                    {loading ? (
                        <div className="flex justify-center py-10">
                            <Spinner />
                        </div>
                    ) : details?.kind === 'options' ? (
                        <OptionsList sections={options} />
                    ) : (
                        <CharacteristicsList sections={characteristics ?? []} />
                    )}
                </DialogContent>
            </Dialog>
        </>
    );
}

export function OptionsList({ sections }: { sections: OptionSection[] }) {
    if (sections.length === 0) {
        return (
            <p className="py-6 text-center text-sm text-muted-foreground">
                Опции для этой комплектации не указаны.
            </p>
        );
    }

    return (
        <div className="flex flex-col gap-5">
            {sections.map((section) => (
                <section key={section.group} className="flex flex-col gap-2">
                    <h4 className="text-sm font-semibold">{section.group}</h4>
                    <ul className="grid gap-1.5 sm:grid-cols-2">
                        {section.items.map((item) => (
                            <li
                                key={item.name}
                                className="flex items-start gap-2 text-sm"
                            >
                                {item.availability === 'standard' ? (
                                    <CircleCheck
                                        className="mt-0.5 size-4 shrink-0 text-emerald-600 dark:text-emerald-400"
                                        aria-label="В базе"
                                    />
                                ) : (
                                    <CirclePlus
                                        className="mt-0.5 size-4 shrink-0 text-muted-foreground"
                                        aria-label="Опция"
                                    />
                                )}
                                <span>{item.name}</span>
                            </li>
                        ))}
                    </ul>
                </section>
            ))}
            <p className="flex flex-wrap gap-4 border-t pt-3 text-xs text-muted-foreground">
                <span className="flex items-center gap-1">
                    <CircleCheck
                        className="size-3.5 text-emerald-600 dark:text-emerald-400"
                        aria-hidden="true"
                    />
                    В базе
                </span>
                <span className="flex items-center gap-1">
                    <CirclePlus className="size-3.5" aria-hidden="true" />
                    За доплату
                </span>
            </p>
        </div>
    );
}

export function CharacteristicsList({
    sections,
}: {
    sections: CharacteristicSection[];
}) {
    if (sections.length === 0) {
        return (
            <p className="py-6 text-center text-sm text-muted-foreground">
                Характеристики для этой модификации не указаны.
            </p>
        );
    }

    return (
        <div className="flex flex-col gap-5">
            {sections.map((section) => (
                <section key={section.group} className="flex flex-col gap-1">
                    <h4 className="text-sm font-semibold">{section.group}</h4>
                    <dl className="divide-y text-sm">
                        {section.items.map((item) => (
                            <div
                                key={item.label}
                                className="flex justify-between gap-4 py-1.5"
                            >
                                <dt className="text-muted-foreground">
                                    {item.label}
                                </dt>
                                <dd className="text-right font-medium">
                                    {item.value}
                                </dd>
                            </div>
                        ))}
                    </dl>
                </section>
            ))}
        </div>
    );
}
